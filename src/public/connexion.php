<?php
/**
 * Exemple complet : page de connexion.
 *
 * Ce fichier montre l'assemblage des six protections sur un cas réel —
 * c'est le point d'entrée le plus attaqué d'une application.
 *
 *   1. Injection SQL  → requêtes préparées (Database)
 *   2. XSS            → échappement en sortie + CSP (Html)
 *   3. CSRF           → jeton par formulaire + SameSite (Csrf, Session)
 *   4. Force brute    → verrouillage compte + IP + délai constant (BruteForce)
 *   5. Mots de passe  → Argon2id + rehash transparent (Password)
 *   6. RGPD           → journalisation, minimisation des messages (Rgpd)
 */

declare(strict_types=1);

require_once __DIR__ . '/../security/Database.php';
require_once __DIR__ . '/../security/Session.php';
require_once __DIR__ . '/../security/Csrf.php';
require_once __DIR__ . '/../security/Html.php';
require_once __DIR__ . '/../security/Password.php';
require_once __DIR__ . '/../security/BruteForce.php';
require_once __DIR__ . '/../security/Validation.php';
require_once __DIR__ . '/../security/Rgpd.php';

use ZenCamp\Security\{BruteForce, Csrf, Database, Html, Password, Rgpd, Session, Validation};

$nonce = Html::nonce();
Html::entetesSecurite($nonce);   // CSP, X-Frame-Options, nosniff…
Session::start();

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Chronomètre : sert à imposer une durée de réponse constante.
    $debut = microtime(true);

    // --- 3. CSRF : refuse la requête si le jeton est absent ou invalide ---
    Csrf::exigerPost('connexion');

    // --- Validation des entrées (liste blanche) -------------------------
    $email    = Validation::email($_POST['email'] ?? null);
    $password = is_string($_POST['motdepasse'] ?? null) ? $_POST['motdepasse'] : '';
    $ip       = BruteForce::ipClient();

    if ($email === null || $password === '') {
        $erreur = 'Identifiants incorrects.';
    } else {
        // --- 4. Force brute : contrôle AVANT toute vérification ---------
        $limite = BruteForce::verifier($email, $ip);

        if (!$limite['autorise']) {
            $erreur = $limite['message'];
        } else {
            // --- 1. Injection SQL : requête préparée, aucune concaténation
            $user = Database::fetchOne(
                'SELECT id_utilisateur, mot_de_passe, role, actif,
                        email_verifie, doit_changer_mdp
                 FROM   utilisateur
                 WHERE  email = :email
                   AND  date_anonymisation IS NULL',
                ['email' => $email]
            );

            if ($user === null) {
                // Compte inexistant : on dépense quand même le temps CPU
                // d'un hachage, sinon le temps de réponse trahit
                // l'existence du compte (énumération).
                Password::hachageFactice();
                BruteForce::enregistrer($email, $ip, false);

                // Message VOLONTAIREMENT identique dans tous les cas
                // d'échec : ne jamais dire « cet e-mail est inconnu ».
                $erreur = 'Identifiants incorrects.';

            // --- 5. Vérification du mot de passe haché ------------------
            } elseif (!Password::verifier($password, $user['mot_de_passe'])) {
                BruteForce::enregistrer($email, $ip, false);
                $erreur = 'Identifiants incorrects.';

            } elseif (!$user['actif']) {
                BruteForce::enregistrer($email, $ip, false);
                $erreur = 'Identifiants incorrects.';

            } else {
                // --- Succès -------------------------------------------
                BruteForce::enregistrer($email, $ip, true);

                // Rehash transparent : seul moment où le mot de passe en
                // clair est disponible pour monter les paramètres Argon2id.
                if (Password::doitEtreRehache($user['mot_de_passe'])) {
                    Database::query(
                        'UPDATE utilisateur
                         SET mot_de_passe = :hash, date_modif_mdp = NOW()
                         WHERE id_utilisateur = :uid',
                        [
                            'hash' => Password::hacher($password),
                            'uid'  => $user['id_utilisateur'],
                        ]
                    );
                }

                // Régénère l'identifiant de session (anti-fixation).
                Session::connecter(
                    (int) $user['id_utilisateur'],
                    (string) $user['role']
                );

                // --- 6. RGPD : traçabilité de la connexion --------------
                Rgpd::journaliser(
                    (int) $user['id_utilisateur'],
                    (int) $user['id_utilisateur'],
                    'connexion_reussie',
                    'utilisateur',
                    (int) $user['id_utilisateur']
                );

                BruteForce::attendreDureeConstante($debut);

                $destination = $user['doit_changer_mdp']
                    ? '/changer-mot-de-passe.php'
                    : '/mon-compte.php';

                header('Location: ' . $destination);
                exit;
            }
        }
    }

    // Même durée de réponse pour tous les échecs.
    BruteForce::attendreDureeConstante($debut);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion — ZenCamp</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <main id="connexion">
        <h1>Connexion</h1>

        <?php if ($erreur !== ''): ?>
            <!-- 2. XSS : toute donnée affichée passe par Html::e() -->
            <p class="erreur" role="alert"><?= Html::e($erreur) ?></p>
        <?php endif; ?>

        <form method="post" action="/connexion.php" autocomplete="on">
            <?= Csrf::champ('connexion') /* jeton CSRF à usage unique */ ?>

            <div class="field">
                <label for="email">Adresse e-mail</label>
                <input type="email" name="email" id="email" required
                       maxlength="180" autocomplete="username"
                       value="<?= Html::e($_POST['email'] ?? '') ?>">
            </div>

            <div class="field">
                <label for="motdepasse">Mot de passe</label>
                <input type="password" name="motdepasse" id="motdepasse" required
                       maxlength="128" autocomplete="current-password">
            </div>

            <ul class="actions">
                <li><input type="submit" value="Se connecter" class="primary"></li>
                <li><a href="/mot-de-passe-oublie.php">Mot de passe oublié ?</a></li>
            </ul>
        </form>
    </main>

    <!-- Le nonce autorise ce script précis malgré la CSP stricte.
         Aucun script inline non nonçé ne sera exécuté par le navigateur. -->
    <script nonce="<?= Html::e($nonce) ?>">
        // Anti double-soumission. Le setTimeout est indispensable :
        // désactiver le bouton dans le handler empêcherait l'envoi.
        document.querySelector('form').addEventListener('submit', function (e) {
            var bouton = e.submitter;
            if (bouton) { setTimeout(function () { bouton.disabled = true; }, 0); }
        });
    </script>
</body>
</html>
