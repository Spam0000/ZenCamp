<?php
declare(strict_types=1);

namespace ZenCamp\Security;

use PDO;
use PDOException;

/**
 * Accès à la base — protection contre l'injection SQL.
 *
 * Principe : AUCUNE donnée utilisateur n'est concaténée dans une requête.
 * Tout passe par des requêtes préparées, où le pilote envoie la requête et
 * les valeurs séparément : MySQL ne peut donc jamais réinterpréter une
 * valeur comme de la syntaxe SQL.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function get(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $config = require __DIR__ . '/../config/config.php';
        $db     = $config['db'];

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $db['host'],
            $db['port'],
            $db['name']
        );

        try {
            self::$pdo = new PDO($dsn, $db['user'], $db['password'], [
                // Les exceptions rendent impossible l'oubli d'un test d'erreur.
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

                // CRUCIAL : désactive l'émulation. Les requêtes préparées sont
                // réellement préparées côté serveur MySQL. Avec l'émulation
                // (défaut PDO), PDO reconstruit la requête en PHP — historiquement
                // source de contournements liés au jeu de caractères.
                PDO::ATTR_EMULATE_PREPARES   => false,

                // Interdit l'exécution de plusieurs requêtes en un seul appel :
                // neutralise les injections « empilées » (`; DROP TABLE …`).
                PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
            ]);
        } catch (PDOException $e) {
            // On ne fuite JAMAIS le message SQL vers l'utilisateur : il révèle
            // la structure de la base et facilite l'injection à l'aveugle.
            error_log('[DB] ' . $e->getMessage());
            http_response_code(500);
            exit('Une erreur technique est survenue.');
        }

        return self::$pdo;
    }

    /**
     * Exécute une requête préparée et renvoie le statement.
     *
     * @param array<string,mixed> $params
     */
    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** @return array<string,mixed>|null */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** @return list<array<string,mixed>> */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /**
     * Cas particulier : ORDER BY et LIMIT ne peuvent PAS être paramétrés
     * (ce sont des éléments de syntaxe, pas des valeurs). La seule défense
     * correcte est une liste blanche — jamais un échappement.
     *
     * @param list<string> $colonnesAutorisees
     */
    public static function orderBySafe(
        string $colonneDemandee,
        string $sensDemande,
        array $colonnesAutorisees,
        string $defaut
    ): string {
        $colonne = in_array($colonneDemandee, $colonnesAutorisees, true)
            ? $colonneDemandee
            : $defaut;
        $sens = strtoupper($sensDemande) === 'DESC' ? 'DESC' : 'ASC';

        return " ORDER BY {$colonne} {$sens}";
    }

    public static function beginTransaction(): void { self::get()->beginTransaction(); }
    public static function commit(): void           { self::get()->commit(); }
    public static function rollBack(): void
    {
        if (self::get()->inTransaction()) {
            self::get()->rollBack();
        }
    }
}
