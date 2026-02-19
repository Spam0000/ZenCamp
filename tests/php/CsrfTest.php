<?php
declare(strict_types=1);

use ZenCamp\Security\Csrf;

/**
 * Protection CSRF : jeton imprevisible, lie a une action, a usage unique.
 * La session est demarree par le lanceur avant l execution de cette suite.
 */

Test::cas('SEC-43', 'Le jeton genere est imprevisible et de longueur suffisante', function () {
    $a = Csrf::token('reservation');
    $b = Csrf::token('reservation');

    Test::estIdentique(64, strlen($a), '32 octets en hexadecimal');
    Test::estVrai($a !== $b, 'deux jetons ne doivent jamais etre identiques');
    Test::estVrai((bool) preg_match('/^[0-9a-f]{64}$/', $a), 'format hexadecimal attendu');
});

Test::cas('SEC-44', 'Un jeton valide est accepte pour son action', function () {
    $token = Csrf::token('reservation');

    Test::estVrai(Csrf::verifier($token, 'reservation'));
});

Test::cas('SEC-45', 'Un jeton est a usage unique (protection contre le rejeu)', function () {
    $token = Csrf::token('reservation');

    Test::estVrai(Csrf::verifier($token, 'reservation'), 'premier usage accepte');
    Test::estFaux(Csrf::verifier($token, 'reservation'), 'second usage refuse');
});

Test::cas('SEC-46', 'Un jeton valide pour une autre action est refuse', function () {
    $token = Csrf::token('reservation');

    Test::estFaux(Csrf::verifier($token, 'suppression_compte'), 'le jeton est lie a une action');
});

Test::cas('SEC-47', 'Un jeton absent, vide ou invente est refuse', function () {
    Test::estFaux(Csrf::verifier(null, 'reservation'), 'jeton absent');
    Test::estFaux(Csrf::verifier('', 'reservation'), 'jeton vide');
    Test::estFaux(Csrf::verifier(str_repeat('a', 64), 'reservation'), 'jeton invente');
});

Test::cas('SEC-48', 'Le champ cache est pret a inserer et sa valeur est echappee', function () {
    $champ = Csrf::champ('reservation');

    Test::contient('type="hidden"', $champ);
    Test::contient('name="csrf_token"', $champ);
    Test::estVrai((bool) preg_match('/value="[0-9a-f]{64}"/', $champ), 'valeur hexadecimale attendue');
});

Test::cas('SEC-49', 'Le nombre de jetons stockes en session reste borne', function () {
    for ($i = 0; $i < 30; $i++) {
        Csrf::token('formulaire_' . $i);
    }

    Test::estVrai(
        count($_SESSION['_csrf_tokens']) <= 20,
        'au plus 20 jetons conserves, obtenu ' . count($_SESSION['_csrf_tokens'])
    );
});

Test::cas('SEC-50', 'Un jeton expire est refuse', function () {
    $token = Csrf::token('reservation');

    // On simule le passage du temps en antidatant l expiration.
    $_SESSION['_csrf_tokens'][$token]['expire'] = time() - 1;

    Test::estFaux(Csrf::verifier($token, 'reservation'), 'jeton perime');
});
