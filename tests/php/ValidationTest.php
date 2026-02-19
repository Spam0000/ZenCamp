<?php
declare(strict_types=1);

use ZenCamp\Security\Validation;

/**
 * Validation des entrees en liste blanche.
 * Exigence : aucune donnee non conforme ne doit atteindre la couche metier.
 */

Test::cas('SEC-01', 'Un e-mail valide est accepte et normalise en minuscules', function () {
    Test::estIdentique('client@zencamp.fr', Validation::email('Client@ZenCamp.FR'));
    Test::estIdentique('a.b+tag@domaine.co.uk', Validation::email('  a.b+tag@domaine.co.uk  '));
});

Test::cas('SEC-02', 'Un e-mail malforme est rejete', function () {
    foreach (['pas-un-email', 'a@', '@b.fr', 'a@b', 'a b@c.fr', ''] as $mauvais) {
        Test::estNull(Validation::email($mauvais), 'e-mail « ' . $mauvais . ' »');
    }
});

Test::cas('SEC-03', 'Une tentative d injection SQL dans l e-mail est rejetee', function () {
    Test::estNull(Validation::email("' OR '1'='1' -- "));
    Test::estNull(Validation::email("admin@zencamp.fr'; DROP TABLE utilisateur; --"));
});

Test::cas('SEC-04', 'Les caracteres de controle sont retires du texte libre', function () {
    Test::estIdentique('Sejour yoga', Validation::texte("Sejour\x00 yoga"));
    Test::estIdentique('Bonjour', Validation::texte("  \x07Bonjour\x1F  "));
});

Test::cas('SEC-05', 'Un texte depassant la longueur maximale est rejete', function () {
    Test::estNull(Validation::texte(str_repeat('a', 256), 255));
    Test::estIdentique(str_repeat('a', 255), Validation::texte(str_repeat('a', 255), 255));
});

Test::cas('SEC-06', 'Un entier hors bornes est rejete', function () {
    Test::estIdentique(4, Validation::entier('4', 1, 8));
    Test::estNull(Validation::entier('9', 1, 8), 'au-dessus du maximum');
    Test::estNull(Validation::entier('0', 1, 8), 'sous le minimum');
    Test::estNull(Validation::entier('2 OR 1=1', 1, 8), 'injection');
    Test::estNull(Validation::entier('abc', 1, 8), 'non numerique');
});

Test::cas('SEC-07', 'Une date inexistante au calendrier est rejetee', function () {
    Test::estIdentique('2026-02-28', Validation::date('2026-02-28'));
    Test::estNull(Validation::date('2026-02-31'), '31 fevrier');
    Test::estNull(Validation::date('2026-13-01'), 'mois 13');
    Test::estNull(Validation::date('28/02/2026'), 'mauvais format');
});

Test::cas('SEC-08', 'Le telephone est accepte quel que soit le separateur saisi', function () {
    Test::estIdentique('0612345678', Validation::telephone('06 12 34 56 78'));
    Test::estIdentique('0612345678', Validation::telephone('06.12.34.56.78'));
    Test::estIdentique('0612345678', Validation::telephone('06-12-34-56-78'));
    Test::estIdentique('+33612345678', Validation::telephone('+33 6 12 34 56 78'));
});

Test::cas('SEC-09', 'Un telephone invalide est rejete', function () {
    Test::estNull(Validation::telephone('123'), 'trop court');
    Test::estNull(Validation::telephone('06123456789'), 'trop long');
    Test::estNull(Validation::telephone('06 12 34 56 7A'), 'lettre');
});

Test::cas('SEC-10', 'Une valeur hors liste fermee est rejetee (defense ORDER BY / ENUM)', function () {
    $cottages = ['heron', 'roseliere', 'martin-pecheur'];
    Test::estIdentique('heron', Validation::parmi('heron', $cottages));
    Test::estNull(Validation::parmi('villa-inconnue', $cottages));
    Test::estNull(Validation::parmi('heron; DROP TABLE reservation', $cottages));
});

Test::cas('SEC-11', 'Le nom de fichier televerse n est jamais celui fourni par le client', function () {
    $nom = Validation::nomFichierSur('jpg');
    Test::estVrai((bool) preg_match('/^[0-9a-f]{32}\.jpg$/', $nom), 'nom aleatoire attendu, obtenu ' . $nom);
    Test::neContientPas('..', $nom, 'traversee de repertoire');
    Test::estVrai($nom !== Validation::nomFichierSur('jpg'), 'deux appels doivent differer');
});
