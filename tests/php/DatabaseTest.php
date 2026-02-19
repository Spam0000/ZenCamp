<?php
declare(strict_types=1);

use ZenCamp\Security\Database;

/**
 * Injection SQL.
 *
 * Les requetes preparees sont testees sur le serveur de recette (voir le
 * plan de tests). Ici on verifie sans base la seule partie de la requete
 * qui ne peut PAS etre parametree : ORDER BY / sens de tri.
 */

Test::cas('SEC-36', 'Une colonne de tri autorisee est reprise telle quelle', function () {
    Test::estIdentique(
        ' ORDER BY prix ASC',
        Database::orderBySafe('prix', 'asc', ['prix', 'date_debut', 'titre'], 'date_debut')
    );
});

Test::cas('SEC-37', 'Une colonne de tri inconnue retombe sur la valeur par defaut', function () {
    Test::estIdentique(
        ' ORDER BY date_debut ASC',
        Database::orderBySafe('colonne_inexistante', 'asc', ['prix', 'date_debut'], 'date_debut')
    );
});

Test::cas('SEC-38', 'Une injection dans ORDER BY est neutralisee par la liste blanche', function () {
    $sql = Database::orderBySafe(
        'prix; DROP TABLE reservation --',
        'asc',
        ['prix', 'date_debut'],
        'date_debut'
    );

    Test::estIdentique(' ORDER BY date_debut ASC', $sql);
    Test::neContientPas('DROP', $sql, 'aucune syntaxe injectee ne doit subsister');
    Test::neContientPas(';', $sql, 'aucune requete empilee possible');
});

Test::cas('SEC-39', 'Une injection UNION dans ORDER BY est neutralisee', function () {
    $sql = Database::orderBySafe(
        'prix UNION SELECT mot_de_passe FROM utilisateur',
        'desc',
        ['prix'],
        'prix'
    );

    Test::estIdentique(' ORDER BY prix DESC', $sql);
    Test::neContientPas('UNION', $sql);
});

Test::cas('SEC-40', 'Le sens de tri est reduit a ASC ou DESC', function () {
    Test::estIdentique(' ORDER BY prix DESC', Database::orderBySafe('prix', 'DESC', ['prix'], 'prix'));
    Test::estIdentique(' ORDER BY prix DESC', Database::orderBySafe('prix', 'desc', ['prix'], 'prix'));
    Test::estIdentique(' ORDER BY prix ASC', Database::orderBySafe('prix', 'ASC', ['prix'], 'prix'));

    // Toute autre valeur retombe sur ASC : rien d autre ne peut etre injecte.
    Test::estIdentique(
        ' ORDER BY prix ASC',
        Database::orderBySafe('prix', 'ASC, (SELECT 1 FROM utilisateur)', ['prix'], 'prix')
    );
});

Test::cas('SEC-41', 'La configuration PDO interdit l emulation et les requetes empilees', function () {
    $source = file_get_contents(dirname(__DIR__, 2) . '/src/security/Database.php');

    Test::contient('PDO::ATTR_EMULATE_PREPARES   => false', $source, 'preparation cote serveur MySQL');
    Test::contient('PDO::MYSQL_ATTR_MULTI_STATEMENTS => false', $source, 'injections empilees interdites');
    Test::contient('PDO::ERRMODE_EXCEPTION', $source, 'aucune erreur silencieuse');
    Test::contient('charset=utf8mb4', $source, 'jeu de caracteres fixe dans le DSN');
});

Test::cas('SEC-42', 'Le message d erreur SQL n est jamais renvoye au client', function () {
    $source = file_get_contents(dirname(__DIR__, 2) . '/src/security/Database.php');

    Test::contient("exit('Une erreur technique est survenue.')", $source, 'message generique');
    Test::contient('error_log(', $source, 'le detail part dans le journal serveur');
});
