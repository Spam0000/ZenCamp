# Tests — ZenCamp

Campagne de tests du projet. Le détail des cas, les captures de code et les
résultats d'exécution sont consignés dans le plan de tests, livrable du
dossier de projet conservé hors dépôt.

## Arborescence

```
tests/
├── js/
│   ├── telephone.test.js    Tests unitaires du formatage (TU-01 à TU-10)
│   └── formulaire.test.js   Tests structurels du formulaire (TS-01 à TS-08)
└── php/
    ├── bootstrap.php        Micro-cadre de test + chargement des classes
    ├── run.php              Lanceur de la campagne
    ├── ValidationTest.php   SEC-01 à SEC-11  — validation des entrées
    ├── PasswordTest.php     SEC-12 à SEC-23  — hachage Argon2id
    ├── HtmlTest.php         SEC-24 à SEC-35  — XSS
    ├── DatabaseTest.php     SEC-36 à SEC-42  — injection SQL
    └── CsrfTest.php         SEC-43 à SEC-50  — CSRF
```

## Exécution

### JavaScript — nécessite Node.js 18 ou plus

```bash
node --test tests/js/*.test.js
```

### PHP — nécessite PHP 8.1 ou plus, avec `mbstring` et `openssl`

```bash
php tests/php/run.php
```

Le lanceur renvoie le code de sortie `0` si tous les cas passent, `1` sinon :
il peut donc être branché tel quel sur une intégration continue.

## Conventions

- Un identifiant unique par cas (`TU-`, `TS-`, `SEC-`), repris à l'identique
  dans le code et dans le plan de tests.
- Les cas de sécurité utilisent des charges d'attaque réelles (injection SQL,
  script XSS, rejeu de jeton CSRF) plutôt que des données neutres.

## Couverture

| Campagne | Cas | Résultat |
|---|---|---|
| Tests unitaires JavaScript | 10 | 10 conformes |
| Tests structurels du formulaire | 8 | 8 conformes |
| Tests de sécurité PHP | 50 | 50 conformes |

Non couverts automatiquement : `BruteForce`, `Session` et `Rgpd`, qui
supposent une base MySQL et un contexte HTTP réel — ils relèvent de la
recette d'intégration décrite dans le plan de tests.
