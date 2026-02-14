<?php
declare(strict_types=1);

namespace ZenCamp\Security;

/**
 * Protection XSS (Cross-Site Scripting).
 *
 * Règle fondamentale : on n'échappe PAS à l'entrée, on échappe à la SORTIE,
 * et avec la fonction correspondant au contexte d'insertion. Une même donnée
 * s'échappe différemment selon qu'elle atterrit dans du texte HTML, un
 * attribut, une URL ou du JavaScript.
 *
 * Nettoyer à l'entrée est une erreur classique : on corrompt la donnée
 * stockée (« L'Hôtel » devient « L&#039;Hôtel » en base) et on reste
 * vulnérable dès qu'une donnée arrive par un autre chemin (import, API,
 * back-office).
 */
final class Html
{
    /**
     * Contexte : texte HTML et attributs entre guillemets.
     * ENT_QUOTES échappe aussi les apostrophes ; ENT_SUBSTITUTE évite qu'un
     * octet UTF-8 invalide produise une chaîne vide (contournement connu).
     */
    public static function e(?string $valeur): string
    {
        return htmlspecialchars(
            $valeur ?? '',
            ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
            'UTF-8'
        );
    }

    /** Raccourci d'affichage : echo Html::out($x); */
    public static function out(?string $valeur): string
    {
        return self::e($valeur);
    }

    /**
     * Contexte : valeur insérée dans une URL (query string).
     */
    public static function url(?string $valeur): string
    {
        return rawurlencode($valeur ?? '');
    }

    /**
     * Contexte : href/src. Bloque les schémas dangereux — `javascript:`,
     * `data:`, `vbscript:` — qui exécutent du code au clic.
     */
    public static function lien(?string $url): string
    {
        $url = trim($url ?? '');

        // On retire les caractères de contrôle utilisés pour masquer le schéma
        // (ex. "java\0script:" ou "java\tscript:").
        $url = preg_replace('/[\x00-\x20]/', '', $url) ?? '';

        $schemasAutorises = ['http', 'https', 'mailto', 'tel'];
        $schema = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        // URL relative (pas de schéma) : autorisée si elle ne commence pas
        // par // (qui serait un lien protocol-relative vers un autre domaine).
        if ($schema === '') {
            return str_starts_with($url, '//') ? '#' : self::e($url);
        }

        return in_array($schema, $schemasAutorises, true) ? self::e($url) : '#';
    }

    /**
     * Contexte : injection d'une valeur PHP dans un bloc <script>.
     * json_encode avec ces drapeaux échappe <, >, & et les guillemets,
     * ce qui empêche de refermer la balise script prématurément.
     */
    public static function js(mixed $valeur): string
    {
        return json_encode(
            $valeur,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }

    /**
     * Contexte : contenu riche autorisé (description d'un séjour saisie au
     * back-office). On applique une liste blanche stricte de balises.
     *
     * En production, préférer une bibliothèque dédiée (HTMLPurifier) :
     * écrire soi-même un filtre HTML complet est un piège à failles.
     */
    public static function richeSimple(?string $html): string
    {
        $balisesAutorisees = '<p><br><strong><em><ul><ol><li><h3><h4>';
        $propre = strip_tags($html ?? '', $balisesAutorisees);

        // strip_tags conserve les attributs : on retire tout attribut, ce qui
        // neutralise onclick, onerror, style, href javascript:, etc.
        return preg_replace('/<([a-z0-9]+)[^>]*>/i', '<$1>', $propre) ?? '';
    }

    /**
     * En-têtes de sécurité HTTP. À appeler avant toute sortie.
     *
     * La CSP est la défense la plus forte contre le XSS : même si une
     * injection passe, le navigateur refuse d'exécuter un script non autorisé.
     */
    public static function entetesSecurite(string $nonce = ''): void
    {
        if (headers_sent()) {
            return;
        }

        $scriptSrc = $nonce !== ""
            ? "'self' 'nonce-{$nonce}'"
            : "'self'";

        header("Content-Security-Policy: "
            . "default-src 'self'; "
            . "script-src {$scriptSrc}; "
            . "style-src 'self'; "
            . "img-src 'self' data:; "
            . "font-src 'self'; "
            . "connect-src 'self'; "
            . "form-action 'self'; "
            // Empêche l'inclusion du site dans une iframe tierce (clickjacking).
            . "frame-ancestors 'none'; "
            . "base-uri 'self'; "
            . "object-src 'none'"
        );

        // Interdit au navigateur de « deviner » un type MIME : une image
        // contenant du HTML ne sera pas exécutée comme telle.
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

        // HSTS : impose HTTPS pour 1 an. À n'activer qu'une fois le
        // certificat en place et vérifié.
        if (($_SERVER['HTTPS'] ?? '') === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        // Le nom et la version de PHP renseignent l'attaquant sur les
        // vulnérabilités connues applicables.
        header_remove('X-Powered-By');
    }

    /** Nonce à usage unique pour autoriser un <script> précis via la CSP. */
    public static function nonce(): string
    {
        return base64_encode(random_bytes(16));
    }
}
