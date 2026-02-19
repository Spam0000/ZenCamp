<?php
declare(strict_types=1);

/**
 * Lanceur des tests de securite ZenCamp.
 * Usage : php tests/php/run.php
 */

require __DIR__ . '/bootstrap.php';

// La session doit etre ouverte avant tout affichage (suite CSRF).
ZenCamp\Security\Session::start();

echo "ZenCamp - Tests de securite (PHP " . PHP_VERSION . ")\n";
echo str_repeat('=', 64), "\n";

foreach (['ValidationTest', 'PasswordTest', 'HtmlTest', 'DatabaseTest', 'CsrfTest'] as $suite) {
    $fichier = __DIR__ . '/' . $suite . '.php';

    if (!is_file($fichier)) {
        continue;
    }

    echo "\n--- ", $suite, " ---\n";
    require $fichier;
}

echo "\n";
exit(Test::bilan());
