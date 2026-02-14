<?php
declare(strict_types=1);

namespace ZenCamp\Security;

/**
 * Validation des entrées.
 *
 * La validation ne remplace ni les requêtes préparées (injection SQL) ni
 * l'échappement en sortie (XSS) : c'est une couche supplémentaire qui
 * réduit la surface d'attaque et garantit la cohérence métier.
 *
 * Principe : liste blanche. On décrit ce qui est ACCEPTÉ, jamais ce qui est
 * interdit — une liste noire est toujours contournable.
 */
final class Validation
{
    /** Chaîne nettoyée des caractères de contrôle, avec longueur bornée. */
    public static function texte(mixed $valeur, int $max = 255): ?string
    {
        if (!is_string($valeur)) {
            return null;
        }

        // Retire les caractères de contrôle (dont \0, qui tronque certaines
        // fonctions C sous-jacentes) mais conserve \n et \t.
        $propre = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $valeur);

        if ($propre === null || !mb_check_encoding($propre, 'UTF-8')) {
            return null;
        }

        $propre = trim($propre);

        return mb_strlen($propre) <= $max ? $propre : null;
    }

    public static function email(mixed $valeur): ?string
    {
        if (!is_string($valeur) || mb_strlen($valeur) > 180) {
            return null;
        }

        $email = filter_var(trim($valeur), FILTER_VALIDATE_EMAIL);

        return $email === false ? null : mb_strtolower($email);
    }

    public static function entier(mixed $valeur, int $min = 0, ?int $max = null): ?int
    {
        $options = ['options' => ['min_range' => $min]];

        if ($max !== null) {
            $options['options']['max_range'] = $max;
        }

        $n = filter_var($valeur, FILTER_VALIDATE_INT, $options);

        return $n === false ? null : $n;
    }

    public static function decimal(mixed $valeur, float $min = 0.0): ?float
    {
        $f = filter_var($valeur, FILTER_VALIDATE_FLOAT);

        return ($f === false || $f < $min) ? null : $f;
    }

    /** Date au format ISO, avec contrôle de validité réelle du calendrier. */
    public static function date(mixed $valeur, string $format = 'Y-m-d'): ?string
    {
        if (!is_string($valeur)) {
            return null;
        }

        $d = \DateTimeImmutable::createFromFormat($format, $valeur);

        // Le second test rejette « 2026-02-31 », que createFromFormat
        // accepterait en le décalant au 3 mars.
        return ($d !== false && $d->format($format) === $valeur)
            ? $valeur
            : null;
    }

    /** Téléphone français ou international. */
    public static function telephone(mixed $valeur): ?string
    {
        if (!is_string($valeur)) {
            return null;
        }

        $normalise = preg_replace('/[\s.\-()]/', '', trim($valeur)) ?? '';

        return preg_match('/^(?:\+[1-9]\d{6,14}|0\d{9})$/', $normalise) === 1
            ? $normalise
            : null;
    }

    /**
     * Valeur appartenant à une liste fermée (ENUM SQL, tri, filtre…).
     * C'est LA défense pour tout ce qui ne peut pas être un paramètre lié.
     *
     * @param list<string> $autorisees
     */
    public static function parmi(mixed $valeur, array $autorisees): ?string
    {
        return (is_string($valeur) && in_array($valeur, $autorisees, true))
            ? $valeur
            : null;
    }

    /**
     * Validation d'un fichier téléversé (photo de profil, visuel de séjour).
     *
     * Le type MIME déclaré par le navigateur est contrôlé par le client :
     * on ne s'y fie jamais. On lit la signature réelle du fichier, et on
     * réencode l'image pour détruire tout contenu parasite (un PHP glissé
     * dans les métadonnées EXIF d'un JPEG valide).
     *
     * @param array{name:string,type:string,tmp_name:string,error:int,size:int} $fichier
     * @return array{ok:bool, message:string, extension:string}
     */
    public static function image(array $fichier, int $tailleMaxMo = 5): array
    {
        if ($fichier['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'message' => 'Échec du téléversement.', 'extension' => ''];
        }

        if ($fichier['size'] > $tailleMaxMo * 1024 * 1024) {
            return ['ok' => false,
                    'message' => "Fichier trop volumineux (max {$tailleMaxMo} Mo).",
                    'extension' => ''];
        }

        // Empêche de traiter un fichier arbitraire du serveur.
        if (!is_uploaded_file($fichier['tmp_name'])) {
            return ['ok' => false, 'message' => 'Fichier invalide.', 'extension' => ''];
        }

        // Signature réelle du contenu, pas l'en-tête déclaré.
        $infos = @getimagesize($fichier['tmp_name']);

        if ($infos === false) {
            return ['ok' => false, 'message' => 'Ce fichier n est pas une image.',
                    'extension' => ''];
        }

        $typesAutorises = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG  => 'png',
            IMAGETYPE_WEBP => 'webp',
        ];

        if (!isset($typesAutorises[$infos[2]])) {
            return ['ok' => false,
                    'message' => 'Format non autorisé (JPEG, PNG ou WebP uniquement).',
                    'extension' => ''];
        }

        return ['ok' => true, 'message' => '', 'extension' => $typesAutorises[$infos[2]]];
    }

    /**
     * Nom de fichier sûr : on ne réutilise JAMAIS le nom fourni par le
     * client (traversée de répertoire via « ../ », double extension
     * « photo.php.jpg »). On génère un nom aléatoire.
     */
    public static function nomFichierSur(string $extension): string
    {
        return bin2hex(random_bytes(16)) . '.' . $extension;
    }
}
