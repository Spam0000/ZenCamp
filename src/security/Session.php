<?php
declare(strict_types=1);

namespace ZenCamp\Security;

/**
 * Gestion durcie des sessions.
 *
 * Le cookie de session est la cible privilégiée : le voler équivaut à voler
 * le mot de passe. Quatre protections :
 *
 *  - HttpOnly : le cookie devient invisible pour JavaScript, donc un XSS
 *               résiduel ne peut pas l'exfiltrer.
 *  - Secure   : le cookie n'est jamais transmis en HTTP clair.
 *  - SameSite=Strict : le navigateur ne joint pas le cookie aux requêtes
 *               venant d'un autre site — seconde barrière anti-CSRF.
 *  - Régénération de l'identifiant à la connexion : neutralise la fixation
 *               de session (l'attaquant impose un identifiant à la victime
 *               avant qu'elle ne se connecte).
 */
final class Session
{
    private const DUREE_INACTIVITE = 1800;  // 30 min
    private const DUREE_ABSOLUE    = 43200; // 12 h
    private const INTERVALLE_REGEN = 900;   // 15 min

    private static bool $demarree = false;

    public static function start(): void
    {
        if (self::$demarree || session_status() === PHP_SESSION_ACTIVE) {
            self::$demarree = true;
            return;
        }

        $https = ($_SERVER['HTTPS'] ?? '') === 'on'
              || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

        session_set_cookie_params([
            'lifetime' => 0,        // cookie de session : effacé à la fermeture
            'path'     => '/',
            'domain'   => '',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        // Nom neutre : « PHPSESSID » annonce la technologie du serveur.
        session_name('zc_session');

        // Refuse un identifiant de session non émis par le serveur.
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');

        session_start();
        self::$demarree = true;

        self::verifierValidite();
    }

    /**
     * Ouvre une session authentifiée.
     * La régénération de l'identifiant est la ligne la plus importante
     * de tout le fichier.
     */
    public static function connecter(int $idUtilisateur, string $role): void
    {
        self::start();

        // Anti-fixation de session : nouvel identifiant, ancien détruit.
        session_regenerate_id(true);

        $_SESSION['id_utilisateur'] = $idUtilisateur;
        $_SESSION['role']           = $role;
        $_SESSION['creation']       = time();
        $_SESSION['derniere_act']   = time();
        $_SESSION['derniere_regen'] = time();

        // Empreinte du client : un cookie volé et rejoué depuis un autre
        // navigateur sera rejeté. On n'inclut PAS l'IP : elle change
        // légitimement (mobile, wifi/4G) et déconnecterait les clients.
        $_SESSION['empreinte'] = self::empreinte();

        self::enregistrerEnBase($idUtilisateur);
    }

    public static function deconnecter(): void
    {
        self::start();

        if (isset($_SESSION['id_utilisateur'])) {
            Database::query(
                'UPDATE session_utilisateur SET revoquee = TRUE
                 WHERE id_session = :sid',
                ['sid' => hash('sha256', session_id())]
            );
        }

        $_SESSION = [];

        // Le cookie doit être explicitement expiré côté navigateur.
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'domain'   => $p['domain'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => 'Strict',
            ]);
        }

        session_destroy();
        self::$demarree = false;
    }

    public static function estConnecte(): bool
    {
        self::start();
        return isset($_SESSION['id_utilisateur']);
    }

    public static function idUtilisateur(): ?int
    {
        self::start();
        return isset($_SESSION['id_utilisateur'])
            ? (int) $_SESSION['id_utilisateur']
            : null;
    }

    /** Contrôle d'accès : à appeler en tête des pages protégées. */
    public static function exigerConnexion(?string $roleRequis = null): void
    {
        self::start();

        if (!self::estConnecte()) {
            header('Location: /connexion.php');
            exit;
        }

        if ($roleRequis !== null && ($_SESSION['role'] ?? '') !== $roleRequis) {
            // 404 plutôt que 403 : ne pas confirmer l'existence de la page
            // d'administration à un utilisateur non habilité.
            http_response_code(404);
            exit('Page introuvable.');
        }
    }

    /**
     * Expiration par inactivité et durée absolue, empreinte client,
     * régénération périodique de l'identifiant.
     */
    private static function verifierValidite(): void
    {
        if (!isset($_SESSION['id_utilisateur'])) {
            return;
        }

        $maintenant = time();

        $inactif = $maintenant - ($_SESSION['derniere_act'] ?? 0) > self::DUREE_INACTIVITE;
        $tropVieille = $maintenant - ($_SESSION['creation'] ?? 0) > self::DUREE_ABSOLUE;
        $empreinteKo = ($_SESSION['empreinte'] ?? '') !== self::empreinte();

        if ($inactif || $tropVieille || $empreinteKo) {
            if ($empreinteKo) {
                error_log('[SESSION] Empreinte client differente - session detruite');
            }
            self::deconnecter();
            return;
        }

        $_SESSION['derniere_act'] = $maintenant;

        // Rotation régulière de l'identifiant : réduit la fenêtre
        // d'exploitation d'un cookie intercepté.
        if ($maintenant - ($_SESSION['derniere_regen'] ?? 0) > self::INTERVALLE_REGEN) {
            session_regenerate_id(true);
            $_SESSION['derniere_regen'] = $maintenant;
        }
    }

    private static function empreinte(): string
    {
        return hash('sha256',
            ($_SERVER['HTTP_USER_AGENT'] ?? '')
            . '|' . ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')
        );
    }

    private static function enregistrerEnBase(int $idUtilisateur): void
    {
        Database::query(
            'INSERT INTO session_utilisateur
                 (id_session, id_utilisateur, adresse_ip, user_agent, date_expiration)
             VALUES
                 (:sid, :uid, INET6_ATON(:ip), :agent,
                  NOW() + INTERVAL 12 HOUR)
             ON DUPLICATE KEY UPDATE date_expiration = VALUES(date_expiration)',
            [
                'sid'   => hash('sha256', session_id()),
                'uid'   => $idUtilisateur,
                'ip'    => BruteForce::ipClient(),
                'agent' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ]
        );
    }

    /**
     * Révoque toutes les sessions d'un utilisateur.
     * À appeler après un changement de mot de passe : si un attaquant était
     * connecté, il est éjecté.
     */
    public static function revoquerToutesLesSessions(int $idUtilisateur): void
    {
        Database::query(
            'UPDATE session_utilisateur SET revoquee = TRUE
             WHERE id_utilisateur = :uid',
            ['uid' => $idUtilisateur]
        );
    }
}
