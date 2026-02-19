<?php
declare(strict_types=1);

/**
 * Micro-cadre de test (sans dependance externe).
 *
 * Chaque cas de test porte un identifiant (SEC-xx) repris dans le plan de
 * tests du dossier de projet, afin de relier une exigence a sa verification.
 */

// Autoload des classes de securite.
spl_autoload_register(static function (string $classe): void {
    $prefixe = 'ZenCamp\\Security\\';

    if (!str_starts_with($classe, $prefixe)) {
        return;
    }

    $fichier = dirname(__DIR__, 2) . '/src/security/'
        . substr($classe, strlen($prefixe)) . '.php';

    if (is_file($fichier)) {
        require_once $fichier;
    }
});

final class Test
{
    /** @var list<array{id:string,titre:string,ok:bool,detail:string}> */
    public static array $resultats = [];

    private static string $idCourant = '';
    private static bool   $echecCourant = false;
    private static string $detailCourant = '';

    public static function cas(string $id, string $titre, callable $scenario): void
    {
        self::$idCourant     = $id;
        self::$echecCourant  = false;
        self::$detailCourant = '';

        try {
            $scenario();
        } catch (\Throwable $e) {
            self::$echecCourant  = true;
            self::$detailCourant = 'Exception : ' . $e->getMessage();
        }

        self::$resultats[] = [
            'id'     => $id,
            'titre'  => $titre,
            'ok'     => !self::$echecCourant,
            'detail' => self::$detailCourant,
        ];

        printf(
            "%s  %-9s %s%s\n",
            self::$echecCourant ? '[ECHEC]' : '[ OK  ]',
            $id,
            $titre,
            self::$echecCourant ? "\n         -> " . self::$detailCourant : ''
        );
    }

    private static function echouer(string $message): void
    {
        self::$echecCourant  = true;
        self::$detailCourant = $message;
    }

    public static function estIdentique(mixed $attendu, mixed $obtenu, string $quoi = ''): void
    {
        if ($attendu !== $obtenu) {
            self::echouer(sprintf(
                '%sattendu %s, obtenu %s',
                $quoi === '' ? '' : $quoi . ' : ',
                var_export($attendu, true),
                var_export($obtenu, true)
            ));
        }
    }

    public static function estVrai(bool $condition, string $quoi = ''): void
    {
        if (!$condition) {
            self::echouer($quoi === '' ? 'condition fausse' : $quoi);
        }
    }

    public static function estFaux(bool $condition, string $quoi = ''): void
    {
        self::estVrai(!$condition, $quoi);
    }

    public static function estNull(mixed $valeur, string $quoi = ''): void
    {
        if ($valeur !== null) {
            self::echouer(($quoi === '' ? '' : $quoi . ' : ')
                . 'valeur non nulle ' . var_export($valeur, true));
        }
    }

    public static function contient(string $aiguille, string $meule, string $quoi = ''): void
    {
        if (!str_contains($meule, $aiguille)) {
            self::echouer(($quoi === '' ? '' : $quoi . ' : ')
                . sprintf('« %s » absent de « %s »', $aiguille, $meule));
        }
    }

    public static function neContientPas(string $aiguille, string $meule, string $quoi = ''): void
    {
        if (str_contains($meule, $aiguille)) {
            self::echouer(($quoi === '' ? '' : $quoi . ' : ')
                . sprintf('« %s » ne devrait pas apparaitre dans « %s »', $aiguille, $meule));
        }
    }

    public static function bilan(): int
    {
        $total  = count(self::$resultats);
        $echecs = count(array_filter(self::$resultats, static fn($r) => !$r['ok']));

        echo str_repeat('-', 64), "\n";
        printf(
            "%d cas executes - %d reussis - %d en echec\n",
            $total,
            $total - $echecs,
            $echecs
        );

        return $echecs === 0 ? 0 : 1;
    }
}
