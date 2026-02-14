<?php
declare(strict_types=1);

namespace ZenCamp\Security;

/**
 * Hachage des mots de passe.
 *
 * Un mot de passe n'est jamais chiffré (le chiffrement est réversible) :
 * il est HACHÉ avec une fonction lente et salée. Même en cas de fuite de
 * la base, retrouver les mots de passe doit rester hors de portée.
 *
 * Algorithme retenu : Argon2id, lauréat de la Password Hashing Competition
 * et recommandé par l'OWASP et l'ANSSI. Contrairement à bcrypt, il est
 * coûteux en MÉMOIRE, ce qui neutralise l'avantage des GPU et des ASIC.
 *
 * À proscrire : MD5, SHA-1, SHA-256 « nu ». Ces fonctions sont conçues pour
 * être rapides — un GPU en calcule des milliards par seconde.
 */
final class Password
{
    /**
     * Paramètres OWASP 2024 pour Argon2id.
     * memory_cost en Kio : 64 Mo par hachage.
     */
    private const OPTIONS = [
        'memory_cost' => 65536, // 64 Mo
        'time_cost'   => 4,     // 4 itérations
        'threads'     => 2,
    ];

    private const LONGUEUR_MIN = 12;
    private const LONGUEUR_MAX = 128; // borne haute : évite un DoS par hachage

    public static function hacher(string $motDePasse): string
    {
        // Le sel est généré automatiquement par PHP et stocké DANS le hash :
        // deux utilisateurs ayant le même mot de passe ont deux hashs
        // différents, ce qui rend les rainbow tables inutilisables.
        $hash = password_hash($motDePasse, PASSWORD_ARGON2ID, self::OPTIONS);

        if ($hash === false) {
            throw new \RuntimeException('Échec du hachage du mot de passe.');
        }

        return $hash;
    }

    /**
     * Vérifie un mot de passe. password_verify compare en temps constant.
     */
    public static function verifier(string $motDePasse, string $hash): bool
    {
        return password_verify($motDePasse, $hash);
    }

    /**
     * Indique s'il faut recalculer le hash (montée des paramètres ou
     * changement d'algorithme). On le fait de façon transparente, à la
     * connexion, seul moment où le mot de passe en clair est disponible.
     */
    public static function doitEtreRehache(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, self::OPTIONS);
    }

    /**
     * Hachage factice, exécuté quand l'e-mail saisi n'existe pas.
     *
     * Sans cela, une réponse instantanée pour un compte inexistant et une
     * réponse lente pour un compte existant permettent d'ÉNUMÉRER les
     * comptes en mesurant le temps de réponse. On dépense donc le même
     * temps CPU dans les deux cas.
     */
    public static function hachageFactice(): void
    {
        password_verify(
            'mot_de_passe_factice',
            '$argon2id$v=19$m=65536,t=4,p=2$'
            . 'ZmFrZXNhbHRmYWtlc2FsdA$'
            . 'K7sN0kZzQ8xJ3vHqYl2mR4tW9bC1dE6fG8hI0jK2lM4'
        );
    }

    /**
     * Politique de mot de passe.
     *
     * On privilégie la LONGUEUR sur la complexité : « cheval agrafe correct
     * batterie » est plus solide et plus mémorisable que « P@ssw0rd! ».
     * C'est aussi la position de la CNIL depuis sa délibération 2022-100 et
     * du NIST SP 800-63B.
     *
     * @return list<string> messages d'erreur (vide si le mot de passe convient)
     */
    public static function valider(string $motDePasse, string $email = ''): array
    {
        $erreurs = [];
        $longueur = mb_strlen($motDePasse);

        if ($longueur < self::LONGUEUR_MIN) {
            $erreurs[] = sprintf(
                'Le mot de passe doit contenir au moins %d caractères.',
                self::LONGUEUR_MIN
            );
        }

        if ($longueur > self::LONGUEUR_MAX) {
            $erreurs[] = sprintf(
                'Le mot de passe ne peut pas dépasser %d caractères.',
                self::LONGUEUR_MAX
            );
        }

        // Pas de mot de passe dérivé de l'identifiant.
        if ($email !== '') {
            $partieLocale = strtolower(explode('@', $email)[0]);
            if ($partieLocale !== ''
                && str_contains(strtolower($motDePasse), $partieLocale)) {
                $erreurs[] = 'Le mot de passe ne doit pas contenir votre adresse e-mail.';
            }
        }

        if (self::estTropCourant($motDePasse)) {
            $erreurs[] = 'Ce mot de passe est trop courant, choisissez-en un autre.';
        }

        if (preg_match('/^(.)\1+$/u', $motDePasse) === 1) {
            $erreurs[] = 'Le mot de passe ne peut pas être une répétition du même caractère.';
        }

        return $erreurs;
    }

    /**
     * Liste noire des mots de passe les plus utilisés.
     *
     * En production, brancher l'API « Pwned Passwords » de Have I Been Pwned
     * en k-anonymat : on envoie les 5 premiers caractères du SHA-1 du mot de
     * passe, jamais le mot de passe lui-même.
     */
    private static function estTropCourant(string $motDePasse): bool
    {
        $liste = [
            'motdepasse', 'password', 'azertyuiop', 'qwertyuiop',
            '123456789012', 'motdepasse123', 'password123', 'zencamp2026',
            'bonjour12345', 'administrateur',
        ];

        $normalise = strtolower($motDePasse);

        foreach ($liste as $courant) {
            if (hash_equals($courant, $normalise)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Jeton aléatoire pour la réinitialisation de mot de passe.
     * On renvoie le jeton en clair (envoyé par e-mail) et son hash SHA-256
     * (seul stocké en base) : une fuite de la base ne permet pas de rejouer
     * les liens de réinitialisation.
     *
     * @return array{clair:string, hash:string}
     */
    public static function genererJeton(): array
    {
        $clair = bin2hex(random_bytes(32));

        // SHA-256 suffit ici : le jeton a déjà 256 bits d'entropie, il n'y a
        // rien à « deviner », donc pas besoin d'une fonction lente.
        return ['clair' => $clair, 'hash' => hash('sha256', $clair)];
    }
}
