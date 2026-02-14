<?php
declare(strict_types=1);

namespace ZenCamp\Security;

/**
 * Protection CSRF (Cross-Site Request Forgery).
 *
 * L'attaque : un site malveillant fait envoyer au navigateur de la victime,
 * déjà authentifiée sur ZenCamp, une requête vers ZenCamp (formulaire
 * auto-soumis, image, fetch). Le navigateur joint automatiquement le cookie
 * de session : côté serveur, la requête semble légitime.
 *
 * La défense : exiger dans chaque requête d'écriture un jeton imprévisible
 * que le site attaquant ne peut pas lire (il est protégé par la Same-Origin
 * Policy) ni deviner.
 *
 * Deuxième barrière indépendante : le cookie SameSite=Strict (voir Session.php),
 * qui empêche le navigateur d'envoyer le cookie sur une requête cross-site.
 */
final class Csrf
{
    private const CLE_SESSION = '_csrf_tokens';
    private const DUREE_VIE   = 7200; // 2 h
    private const MAX_JETONS  = 20;   // limite la taille de la session

    /**
     * Génère un jeton lié à une action donnée.
     * Un jeton par formulaire : le vol d'un jeton ne compromet pas les autres.
     */
    public static function token(string $action = 'default'): string
    {
        Session::start();

        if (!isset($_SESSION[self::CLE_SESSION])) {
            $_SESSION[self::CLE_SESSION] = [];
        }

        self::purger();

        // random_bytes est cryptographiquement sûr — jamais rand() ni uniqid(),
        // qui sont prédictibles.
        $token = bin2hex(random_bytes(32));

        $_SESSION[self::CLE_SESSION][$token] = [
            'action'  => $action,
            'expire'  => time() + self::DUREE_VIE,
        ];

        return $token;
    }

    /**
     * Champ caché prêt à insérer dans un formulaire.
     * La valeur est déjà échappée : elle est hexadécimale, mais on passe
     * quand même par Html::e() par principe de cohérence.
     */
    public static function champ(string $action = 'default'): string
    {
        return sprintf(
            '<input type="hidden" name="csrf_token" value="%s">',
            Html::e(self::token($action))
        );
    }

    /**
     * Vérifie le jeton reçu. Renvoie false et consomme le jeton dans tous
     * les cas de succès (usage unique : empêche le rejeu).
     */
    public static function verifier(?string $token, string $action = 'default'): bool
    {
        Session::start();

        if ($token === null || $token === '') {
            return false;
        }

        $jetons = $_SESSION[self::CLE_SESSION] ?? [];
        if (!isset($jetons[$token])) {
            return false;
        }

        $entree = $jetons[$token];
        unset($_SESSION[self::CLE_SESSION][$token]); // usage unique

        if ($entree['expire'] < time()) {
            return false;
        }

        // hash_equals compare en temps constant : empêche de deviner le jeton
        // octet par octet en mesurant le temps de réponse.
        return hash_equals($entree['action'], $action);
    }

    /**
     * À appeler en tête de tout script traitant une écriture.
     * Refuse la requête plutôt que de la traiter à moitié.
     */
    public static function exigerPost(string $action = 'default'): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit('Méthode non autorisée.');
        }

        if (!self::verifier($_POST['csrf_token'] ?? null, $action)) {
            // On journalise : une salve d'échecs CSRF est un signal d'attaque.
            error_log(sprintf(
                '[CSRF] Jeton invalide - action=%s ip=%s uri=%s',
                $action,
                $_SERVER['REMOTE_ADDR'] ?? '?',
                $_SERVER['REQUEST_URI'] ?? '?'
            ));
            http_response_code(403);
            exit('Requête invalide ou expirée. Merci de recharger la page.');
        }

        // Défense en profondeur : vérification de l'origine.
        self::verifierOrigine();
    }

    /**
     * Contrôle des en-têtes Origin / Referer. Complémentaire du jeton :
     * bloque les requêtes cross-site même si un jeton venait à fuiter.
     */
    private static function verifierOrigine(): void
    {
        $origine = $_SERVER['HTTP_ORIGIN']
            ?? ($_SERVER['HTTP_REFERER'] ?? null);

        if ($origine === null) {
            return; // certains clients légitimes n'envoient rien
        }

        $hoteOrigine = parse_url($origine, PHP_URL_HOST);
        $hoteAttendu = $_SERVER['HTTP_HOST'] ?? '';

        // Comparaison sur l'hôte seul, sans le port.
        $hoteAttendu = explode(':', $hoteAttendu)[0];

        if ($hoteOrigine !== null && !hash_equals($hoteAttendu, $hoteOrigine)) {
            http_response_code(403);
            exit('Origine de la requête non autorisée.');
        }
    }

    private static function purger(): void
    {
        $maintenant = time();

        foreach ($_SESSION[self::CLE_SESSION] as $token => $entree) {
            if ($entree['expire'] < $maintenant) {
                unset($_SESSION[self::CLE_SESSION][$token]);
            }
        }

        // Garde les plus récents si l'utilisateur ouvre beaucoup d'onglets.
        // On réserve une place pour le jeton ajouté juste après cette purge :
        // la session ne contient ainsi jamais plus de MAX_JETONS entrées.
        $limite = self::MAX_JETONS - 1;

        if (count($_SESSION[self::CLE_SESSION]) > $limite) {
            $_SESSION[self::CLE_SESSION] = array_slice(
                $_SESSION[self::CLE_SESSION],
                -$limite,
                null,
                true
            );
        }
    }
}
