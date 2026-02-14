<?php
declare(strict_types=1);

namespace ZenCamp\Security;

/**
 * Protection contre les attaques par force brute et par bourrage
 * d'identifiants (credential stuffing).
 *
 * Trois barrières complémentaires :
 *
 *  1. Par COMPTE     — verrouillage temporaire exponentiel après 5 échecs.
 *  2. Par ADRESSE IP — une IP qui échoue sur beaucoup de comptes différents
 *                      est bloquée même si aucun compte n'atteint son seuil.
 *  3. Délai constant — chaque tentative dure au minimum le même temps, ce
 *                      qui empêche l'énumération des comptes par le timing.
 *
 * Le verrouillage par compte seul est insuffisant : il permet à un attaquant
 * de verrouiller volontairement les comptes des clients (déni de service).
 * D'où le seuil par IP, et un verrouillage temporaire — jamais définitif.
 */
final class BruteForce
{
    // Seuils par compte
    private const ECHECS_AVANT_VERROU = 5;
    private const VERROU_MAX_MINUTES  = 60;

    // Seuils par IP, sur une fenêtre glissante
    private const FENETRE_MINUTES     = 15;
    private const ECHECS_MAX_IP       = 20;
    private const COMPTES_MAX_IP      = 10; // comptes distincts visés

    // Durée plancher d'une tentative, en microsecondes (300 ms)
    private const DUREE_MINIMALE_US   = 300_000;

    /**
     * Vérifie si la tentative est autorisée AVANT toute vérification du
     * mot de passe.
     *
     * @return array{autorise:bool, message:string, attente:int}
     */
    public static function verifier(string $email, string $ip): array
    {
        // --- Barrière 1 : compte verrouillé ? --------------------------
        $user = Database::fetchOne(
            'SELECT verrouille_jusqu_a,
                    TIMESTAMPDIFF(SECOND, NOW(), verrouille_jusqu_a) AS secondes
             FROM   utilisateur
             WHERE  email = :email',
            ['email' => $email]
        );

        if ($user !== null
            && $user['verrouille_jusqu_a'] !== null
            && (int) $user['secondes'] > 0) {
            return [
                'autorise' => false,
                'message'  => 'Trop de tentatives. Réessayez dans '
                    . ceil((int) $user['secondes'] / 60) . ' minute(s).',
                'attente'  => (int) $user['secondes'],
            ];
        }

        // --- Barrière 2 : IP abusive ? ---------------------------------
        // INTERVAL attend un littéral, pas un paramètre lié. On interpole
        // ici une CONSTANTE DE CLASSE entière — jamais une donnée reçue du
        // client : il n'y a donc aucune surface d'injection.
        $fenetre = (int) self::FENETRE_MINUTES;

        $stats = Database::fetchOne(
            "SELECT COUNT(*) AS nb_echecs,
                    COUNT(DISTINCT email_saisi) AS nb_comptes
             FROM   tentative_connexion
             WHERE  adresse_ip = INET6_ATON(:ip)
               AND  succes = 0
               AND  date_tentative > (NOW() - INTERVAL {$fenetre} MINUTE)",
            ['ip' => $ip]
        );

        $nbEchecs  = (int) ($stats['nb_echecs'] ?? 0);
        $nbComptes = (int) ($stats['nb_comptes'] ?? 0);

        if ($nbEchecs >= self::ECHECS_MAX_IP || $nbComptes >= self::COMPTES_MAX_IP) {
            error_log(sprintf(
                '[BRUTEFORCE] IP bloquee ip=%s echecs=%d comptes=%d',
                $ip, $nbEchecs, $nbComptes
            ));

            return [
                'autorise' => false,
                'message'  => 'Trop de tentatives depuis votre connexion. '
                            . 'Réessayez dans ' . self::FENETRE_MINUTES . ' minutes.',
                'attente'  => self::FENETRE_MINUTES * 60,
            ];
        }

        return ['autorise' => true, 'message' => '', 'attente' => 0];
    }

    /**
     * Enregistre le résultat d'une tentative et applique le verrouillage
     * progressif. Délègue à la procédure stockée sp_enregistrer_tentative
     * pour que le compteur et le verrou soient mis à jour atomiquement.
     */
    public static function enregistrer(string $email, string $ip, bool $succes): void
    {
        Database::query(
            'CALL sp_enregistrer_tentative(:email, :ip, :agent, :succes)',
            [
                'email'  => $email,
                'ip'     => $ip,
                'agent'  => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                'succes' => $succes ? 1 : 0,
            ]
        );
    }

    /**
     * Impose une durée plancher à la tentative.
     *
     * Appelée avec le timestamp de début, elle complète le temps écoulé
     * jusqu'à la durée minimale. Une réponse « e-mail inconnu » et une
     * réponse « mot de passe faux » deviennent indiscernables au chronomètre.
     */
    public static function attendreDureeConstante(float $debut): void
    {
        $ecouleUs = (int) ((microtime(true) - $debut) * 1_000_000);
        $resteUs  = self::DUREE_MINIMALE_US - $ecouleUs;

        if ($resteUs > 0) {
            usleep($resteUs);
        }
    }

    /**
     * Récupère l'IP réelle du client.
     *
     * Attention : X-Forwarded-For est trivialement falsifiable. On ne la lit
     * que si la requête vient d'un reverse proxy de confiance, sinon un
     * attaquant contournerait toute la limitation en changeant l'en-tête à
     * chaque requête.
     *
     * @param list<string> $proxiesDeConfiance
     */
    public static function ipClient(array $proxiesDeConfiance = []): string
    {
        $ipDirecte = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if (in_array($ipDirecte, $proxiesDeConfiance, true)
            && isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $chaine = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($chaine[0]);

            if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                return $ip;
            }
        }

        return $ipDirecte;
    }

    /**
     * Limitation générique par action (formulaire de contact, demande de
     * réinitialisation, création de compte…). Empêche le spam et l'abus
     * des envois d'e-mails.
     */
    public static function limiterAction(
        string $action,
        string $ip,
        int $maxParHeure = 5
    ): bool {
        Session::start();
        $cle = "_rl_{$action}";
        $maintenant = time();

        $historique = array_filter(
            $_SESSION[$cle] ?? [],
            static fn (int $t): bool => $t > $maintenant - 3600
        );

        if (count($historique) >= $maxParHeure) {
            $_SESSION[$cle] = $historique;
            return false;
        }

        $historique[] = $maintenant;
        $_SESSION[$cle] = $historique;

        return true;
    }
}
