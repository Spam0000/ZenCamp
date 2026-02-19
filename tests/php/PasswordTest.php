<?php
declare(strict_types=1);

use ZenCamp\Security\Password;

/**
 * Hachage et politique de mot de passe.
 * Exigence : aucun mot de passe stocke en clair, aucun hash rejouable.
 */

Test::cas('SEC-12', 'Le mot de passe est hache en Argon2id, jamais stocke en clair', function () {
    $hash = Password::hacher('correcte batterie cheval agrafe');

    Test::contient('$argon2id$', $hash, 'algorithme attendu');
    Test::neContientPas('correcte batterie', $hash, 'le mot de passe ne doit pas apparaitre');
});

Test::cas('SEC-13', 'Deux hachages du meme mot de passe different (sel aleatoire)', function () {
    $a = Password::hacher('correcte batterie cheval agrafe');
    $b = Password::hacher('correcte batterie cheval agrafe');

    Test::estVrai($a !== $b, 'les rainbow tables doivent rester inutilisables');
});

Test::cas('SEC-14', 'La verification accepte le bon mot de passe et refuse les autres', function () {
    $hash = Password::hacher('correcte batterie cheval agrafe');

    Test::estVrai(Password::verifier('correcte batterie cheval agrafe', $hash), 'bon mot de passe');
    Test::estFaux(Password::verifier('correcte batterie cheval agrafi', $hash), 'un caractere modifie');
    Test::estFaux(Password::verifier('', $hash), 'mot de passe vide');
    Test::estFaux(Password::verifier('CORRECTE BATTERIE CHEVAL AGRAFE', $hash), 'casse differente');
});

Test::cas('SEC-15', 'Un hash aux parametres obsoletes est signale pour rehachage', function () {
    $ancien = password_hash('correcte batterie cheval agrafe', PASSWORD_BCRYPT);

    Test::estVrai(Password::doitEtreRehache($ancien), 'bcrypt doit etre remplace par Argon2id');
    Test::estFaux(
        Password::doitEtreRehache(Password::hacher('correcte batterie cheval agrafe')),
        'un hash a jour ne doit pas etre recalcule'
    );
});

Test::cas('SEC-16', 'Un mot de passe trop court est refuse', function () {
    $erreurs = Password::valider('Zen2026!');

    Test::estVrai($erreurs !== [], 'un mot de passe de 8 caracteres doit etre refuse');
    Test::contient('au moins 12 caractères', implode(' ', $erreurs));
});

Test::cas('SEC-17', 'Une phrase de passe longue est acceptee', function () {
    Test::estIdentique([], Password::valider('correcte batterie cheval agrafe'));
});

Test::cas('SEC-18', 'Un mot de passe trop courant est refuse', function () {
    foreach (['motdepasse123', 'azertyuiop', 'zencamp2026'] as $courant) {
        Test::estVrai(Password::valider($courant) !== [], 'devrait refuser « ' . $courant . ' »');
    }
});

Test::cas('SEC-19', 'Un mot de passe derive de l e-mail est refuse', function () {
    $erreurs = Password::valider('raphael-touzet-2026', 'raphael@zencamp.fr');

    Test::contient('adresse e-mail', implode(' ', $erreurs));
});

Test::cas('SEC-20', 'La repetition d un meme caractere est refusee', function () {
    Test::estVrai(Password::valider('aaaaaaaaaaaaaa') !== [], 'repetition simple');
});

Test::cas('SEC-21', 'Un mot de passe demesure est refuse (protection contre le DoS par hachage)', function () {
    $erreurs = Password::valider(str_repeat('a', 200));

    Test::contient('dépasser 128 caractères', implode(' ', $erreurs));
});

Test::cas('SEC-22', 'Le jeton de reinitialisation n est stocke que sous forme de hash', function () {
    $jeton = Password::genererJeton();

    Test::estIdentique(64, strlen($jeton['clair']), 'jeton de 32 octets en hexadecimal');
    Test::estIdentique(hash('sha256', $jeton['clair']), $jeton['hash'], 'hash SHA-256 du jeton');
    Test::estVrai($jeton['clair'] !== $jeton['hash'], 'la base ne doit jamais contenir le jeton en clair');
    Test::estVrai($jeton['clair'] !== Password::genererJeton()['clair'], 'jeton non predictible');
});

Test::cas('SEC-23', 'Le hachage factice repond meme pour un compte inexistant (anti-enumeration)', function () {
    $debut = microtime(true);
    Password::hachageFactice();
    $duree = microtime(true) - $debut;

    Test::estVrai($duree > 0, 'du temps CPU doit etre depense');
});
