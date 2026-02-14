<?php
/**
 * Configuration ZenCamp — MODÈLE.
 *
 * À copier en `config.php`, qui est ignoré par git (voir .gitignore).
 * Aucun secret ne doit JAMAIS être versionné : un mot de passe poussé sur
 * GitHub est compromis, même après suppression du commit (l'historique et
 * les caches des robots d'indexation le conservent).
 *
 * En production, préférer des variables d'environnement au fichier.
 */

declare(strict_types=1);

return [
    'db' => [
        'host'     => getenv('ZENCAMP_DB_HOST') ?: '127.0.0.1',
        'port'     => (int) (getenv('ZENCAMP_DB_PORT') ?: 3306),
        'name'     => getenv('ZENCAMP_DB_NAME') ?: 'zencamp',

        // Compte à privilèges limités (SELECT/INSERT/UPDATE/DELETE),
        // créé par zencamp_securite.sql. Jamais root.
        'user'     => getenv('ZENCAMP_DB_USER') ?: 'zencamp_app',
        'password' => getenv('ZENCAMP_DB_PASSWORD') ?: '',
    ],

    'app' => [
        // false en production : affiche des erreurs génériques à
        // l'utilisateur et n'écrit les détails que dans les logs serveur.
        'debug'   => filter_var(getenv('ZENCAMP_DEBUG') ?: 'false',
                                FILTER_VALIDATE_BOOL),
        'url'     => getenv('ZENCAMP_URL') ?: 'https://www.zencamp.fr',
        'env'     => getenv('ZENCAMP_ENV') ?: 'production',
    ],

    'securite' => [
        // Clé AES-256 en base64 (32 octets décodés) pour les données de santé.
        // Générer avec : php -r "echo base64_encode(random_bytes(32));"
        'cle_chiffrement' => getenv('ZENCAMP_CLE_CHIFFREMENT') ?: '',

        // Reverse proxies dont on accepte l'en-tête X-Forwarded-For.
        // Liste VIDE si le serveur est exposé directement : sinon un
        // attaquant falsifie son IP et contourne toute limitation.
        'proxies_de_confiance' => [],
    ],

    'rgpd' => [
        'contact_dpo'         => 'dpo@zencamp.fr',
        'version_politique'   => 'v1.2',
        'conservation_compte' => '3 years',
    ],
];
