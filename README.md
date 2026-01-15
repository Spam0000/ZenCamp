# 🧘 ZenCamp

**ZenCamp** est un site vitrine présentant une future plateforme e-commerce dédiée à la vente en ligne de séjours bien-être (retraites de yoga, méditation, digital detox). La plateforme visée proposera des séjours sous différents formats — individuels, groupes ou événements spéciaux — avec une expérience utilisateur sécurisée, intuitive et personnalisée.

Projet réalisé par **Raphaël Touzet**.

---

## 📁 Arborescence du projet

```
ZenCamp/
├── index.html                  # Page unique du site (structure HTML)
├── documentation/              # Dossier de documentation du projet
│   └── ZenCamp_Raphael TOUZET_Sebastien PEREIRA.docx
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

> Le site est un projet **front-end statique** (aucun back-end, base de données ou framework JS à ce stade) : il sert de vitrine/maquette pour la future plateforme e-commerce ZenCamp.

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
- **`documentation/`** — Dossier contenant le rapport de projet (.docx) rédigé par les auteurs.
- **`LICENSE.txt`** — Licence du template de base (HTML5 UP, Creative Commons).

---

## 👥 Auteur

- Raphaël Touzet
