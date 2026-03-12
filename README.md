# 🧘 ZenCamp

[![CI/CD](https://github.com/Spam0000/ZenCamp/actions/workflows/ci-cd.yml/badge.svg)](https://github.com/Spam0000/ZenCamp/actions/workflows/ci-cd.yml)

**ZenCamp** est un site vitrine présentant une future plateforme e-commerce dédiée à la vente en ligne de séjours bien-être (retraites de yoga, méditation, digital detox). La plateforme visée proposera des séjours sous différents formats — individuels, groupes ou événements spéciaux — avec une expérience utilisateur sécurisée, intuitive et personnalisée.

Projet réalisé par **Raphaël Touzet**.

---

## 📁 Arborescence du projet

```
ZenCamp/
├── .github/workflows/
│   └── ci-cd.yml               # Pipeline d’integration et de deploiement continus
├── index.html                  # Page unique du site (structure HTML)
├── Dockerfile                  # Image Apache + PHP 8.3
├── docker-compose.yml          # Pile web + MySQL + phpMyAdmin
├── docker/                     # Configuration des conteneurs
│   ├── apache/zencamp.conf     # VirtualHost, alias /app, en-tetes
│   ├── php/zencamp.ini         # Reglages PHP durcis
│   └── mysql/30-utilisateur-docker.sql  # Compte applicatif reseau Docker
├── src/                        # Back-end (implémentation de référence, PHP 8)
│   ├── config/
│   │   └── config.example.php  # Modèle de configuration (config.php est ignoré par git)
│   ├── security/               # Bibliothèque de sécurité
│   │   ├── Database.php        # PDO, requêtes préparées
│   │   ├── Html.php            # Échappement contextuel, CSP
│   │   ├── Csrf.php            # Jetons anti-CSRF
│   │   ├── Password.php        # Argon2id
│   │   ├── BruteForce.php      # Limitation compte + IP
│   │   ├── Session.php         # Sessions durcies
│   │   ├── Validation.php      # Validation en liste blanche
│   │   └── Rgpd.php            # Consentement, export, effacement
│   ├── public/
│   │   └── connexion.php       # Exemple assemblant les protections
│   └── .htaccess               # Durcissement serveur
├── tests/                      # Campagne de tests (voir tests/README.md)
│   ├── js/                     # Tests unitaires et structurels (Node)
│   └── php/                    # Tests de securite (PHP)
├── images/                     # Visuels utilisés sur le site
│   ├── bg.jpg
│   ├── overlay.png
│   ├── pic01.jpg
│   ├── pic02.jpg
│   └── pic03.jpg
├── assets/
│   ├── css/                    # Feuilles de style compilées
│   │   ├── main.css
│   │   ├── noscript.css
│   │   └── fontawesome-all.min.css
│   ├── sass/                   # Sources Sass (avant compilation)
│   │   ├── main.scss
│   │   ├── noscript.scss
│   │   ├── base/               # Reset CSS, typographie, mise en page globale
│   │   ├── components/         # Boutons, formulaires, icônes, boîtes, tableaux…
│   │   ├── layout/              # Header, footer, wrapper, arrière-plan
│   │   └── libs/                 # Fonctions, mixins, variables, breakpoints
│   ├── js/                     # Scripts JavaScript
│   │   ├── jquery.min.js
│   │   ├── browser.min.js
│   │   ├── breakpoints.min.js
│   │   ├── util.js
│   │   ├── telephone.js        # Formatage du champ telephone (teste)
│   │   └── main.js
│   └── webfonts/               # Polices d'icônes Font Awesome (eot, svg, ttf, woff, woff2)
├── LICENSE.txt
└── README.txt
```

---

## 🛠️ Technologies utilisées

| Domaine        | Technologie                          |
|-----------------|---------------------------------------|
| Structure        | HTML5                                |
| Style             | CSS3, **Sass (SCSS)**                |
| Interactivité      | JavaScript, **jQuery**              |
| Icônes             | Font Awesome                        |
| Base du template   | [Dimension by HTML5 UP](https://html5up.net/dimension) |
| Versionnage         | Git / GitHub                        |
| Base de données     | **MySQL 8 / MariaDB** (InnoDB, utf8mb4) — *conception* |
| Back-end            | **PHP 8 / PDO** — *implémentation de référence* |

> La page publique reste un **front-end statique**. Le dossier `src/` contient une implémentation de référence des mécanismes de sécurité de la future plateforme e-commerce — non branchée sur `index.html` à ce stade. La conception de la base de données et le plan de tests font partie des livrables du dossier de projet, conservés hors dépôt.

---

## 🧩 Schéma simple de l'architecture

```
        ┌────────────────────────────┐
        │        Navigateur          │
        │   (utilisateur / client)   │
        └──────────────┬─────────────┘
                       │ requête HTTP
                       ▼
        ┌────────────────────────────┐
        │        index.html          │
        │   (structure de la page)   │
        └──────────────┬─────────────┘
                       │ charge
          ┌────────────┼────────────┐
          ▼            ▼            ▼
   ┌─────────────┐ ┌──────────┐ ┌───────────┐
   │ assets/css  │ │assets/js │ │  images/  │
   │ (mise en    │ │(interac- │ │ (visuels) │
   │  forme)     │ │ tivité)  │ │           │
   └──────┬──────┘ └──────────┘ └───────────┘
          │ compilé depuis
          ▼
   ┌─────────────┐
   │ assets/sass │
   │ (sources    │
   │  SCSS)      │
   └─────────────┘
```

Le fonctionnement est simple : `index.html` définit le contenu et la structure de la page, qui va chercher sa mise en forme dans `assets/css` (généré à partir des fichiers `assets/sass`) et son interactivité dans `assets/js`. Les `images/` illustrent les différentes sections (accueil, cottage, réservation, contact).

---

## 📌 Organisation des fichiers principaux

- **`index.html`** — Point d'entrée unique du site. Contient toutes les sections : présentation (`#intro`), hébergement/cottage (`#work`), réservation (`#about`), contact (`#contact`) et éléments UI (`#elements`).
- **`assets/sass/main.scss`** — Fichier Sass principal qui importe tous les partiels (`base`, `components`, `layout`, `libs`) et se compile vers `assets/css/main.css`.
- **`assets/css/main.css`** — Feuille de style finale chargée par la page.
- **`assets/js/main.js`** — Script principal gérant les comportements spécifiques au site (menus, animations…).
- **`assets/js/util.js`** — Fonctions utilitaires réutilisées par les autres scripts.
- **`images/`** — Photos et arrière-plans utilisés dans les différentes sections du site.
- **`LICENSE.txt`** — Licence du template de base (HTML5 UP, Creative Commons).

---

## 🐳 Démarrage avec Docker

La pile complète (Apache + PHP 8.3, MySQL 8.4, phpMyAdmin) se lance en une commande.

```bash
cp .env.example .env          # adapter les ports et mots de passe si besoin
docker compose up -d --build
```

| Service | Adresse |
|---|---|
| Vitrine | http://localhost:8080 |
| Exemple de connexion sécurisée | http://localhost:8080/app/connexion.php |
| phpMyAdmin | http://localhost:8081 |
| MySQL | `localhost:3306` |

La base est créée et peuplée au premier démarrage à partir des scripts SQL
du dossier de conception. Pour repartir d’une base vierge :

```bash
docker compose down -v && docker compose up -d
```

### Jouer la campagne de tests

Aucune installation de PHP n’est nécessaire sur le poste :

```bash
docker compose --profile tests run --rm tests
```

### Organisation retenue

L’image reproduit la séparation attendue en production : la racine web
(`/var/www/html`) ne contient que la vitrine statique, tandis que le code PHP
vit dans `/var/www/zencamp`, hors racine web. Seul `src/public` est exposé,
via l’alias `/app`. Le compte MySQL applicatif n’a que les privilèges
`SELECT`, `INSERT`, `UPDATE` et `DELETE`.

---

## ⚙️ Intégration et déploiement continus

Le pipeline [`.github/workflows/ci-cd.yml`](.github/workflows/ci-cd.yml) se déclenche
à chaque push sur `main`, à chaque pull request, et manuellement depuis l’onglet Actions.

| Étape | Contenu |
|---|---|
| Contrôle des secrets | Vérifie qu’aucun `config.php`, `.env` ni mot de passe en dur n’est versionné |
| Tests front | 18 cas (formatage du téléphone, structure du formulaire) sur Node 20 et 22 |
| Tests de sécurité | 50 cas (validation, Argon2id, XSS, injection SQL, CSRF) sur PHP 8.1, 8.2 et 8.3 |
| Image Docker | Construction, démarrage du conteneur, puis vérification que la vitrine répond, que le code PHP n’est pas exposé et que les en-têtes de sécurité sont posés |
| Pile compose | Validation de `docker-compose.yml` |
| Déploiement | Publication de la vitrine sur GitHub Pages, uniquement depuis `main` et si tout le reste est vert |

Le déploiement suppose que GitHub Pages soit activé sur le dépôt, avec
**Settings → Pages → Source : GitHub Actions**.

---

## 👥 Auteur

- Raphaël Touzet
