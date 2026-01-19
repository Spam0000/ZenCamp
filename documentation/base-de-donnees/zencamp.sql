-- =====================================================================
--  ZenCamp — Script de création de la base de données
--  SGBD : MySQL 8.0 / MariaDB 10.5+  |  Moteur : InnoDB  |  utf8mb4
--  Voir MCD-MLD.md pour le modèle conceptuel et logique.
-- =====================================================================

DROP DATABASE IF EXISTS zencamp;
CREATE DATABASE zencamp
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE zencamp;

-- ---------------------------------------------------------------------
-- 1. UTILISATEURS
-- ---------------------------------------------------------------------

CREATE TABLE utilisateur (
    id_utilisateur   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom              VARCHAR(80)  NOT NULL,
    prenom           VARCHAR(80)  NOT NULL,
    email            VARCHAR(180) NOT NULL,
    mot_de_passe     VARCHAR(255) NOT NULL COMMENT 'hash bcrypt/argon2id',
    telephone        VARCHAR(20)      NULL,
    date_naissance   DATE             NULL,
    role             ENUM('client','admin') NOT NULL DEFAULT 'client',
    actif            BOOLEAN      NOT NULL DEFAULT TRUE,
    date_inscription DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_utilisateur),
    UNIQUE KEY uk_utilisateur_email (email)
) ENGINE=InnoDB;

CREATE TABLE adresse (
    id_adresse     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_utilisateur INT UNSIGNED NOT NULL,
    libelle        VARCHAR(60)      NULL COMMENT 'ex. Domicile',
    ligne1         VARCHAR(150) NOT NULL,
    ligne2         VARCHAR(150)     NULL,
    code_postal    VARCHAR(10)  NOT NULL,
    ville          VARCHAR(100) NOT NULL,
    pays           VARCHAR(80)  NOT NULL DEFAULT 'France',
    type_adresse   ENUM('facturation','livraison','autre') NOT NULL DEFAULT 'facturation',
    PRIMARY KEY (id_adresse),
    KEY idx_adresse_utilisateur (id_utilisateur),
    CONSTRAINT fk_adresse_utilisateur FOREIGN KEY (id_utilisateur)
        REFERENCES utilisateur (id_utilisateur)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. CATALOGUE : lieux, hébergements, séjours, thèmes, activités
-- ---------------------------------------------------------------------

CREATE TABLE lieu (
    id_lieu     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom         VARCHAR(120) NOT NULL,
    description TEXT             NULL,
    adresse     VARCHAR(150)     NULL,
    ville       VARCHAR(100) NOT NULL,
    code_postal VARCHAR(10)      NULL,
    pays        VARCHAR(80)  NOT NULL DEFAULT 'France',
    latitude    DECIMAL(9,6)     NULL,
    longitude   DECIMAL(9,6)     NULL,
    photo       VARCHAR(255)     NULL,
    PRIMARY KEY (id_lieu),
    KEY idx_lieu_ville (ville)
) ENGINE=InnoDB;

CREATE TABLE hebergement (
    id_hebergement   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_lieu          INT UNSIGNED NOT NULL,
    nom              VARCHAR(120) NOT NULL,
    type_hebergement ENUM('cottage','yourte','tente','chambre','dortoir','cabane')
                     NOT NULL DEFAULT 'cottage',
    capacite         SMALLINT UNSIGNED NOT NULL,
    prix_nuit        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    description      TEXT              NULL,
    PRIMARY KEY (id_hebergement),
    KEY idx_hebergement_lieu (id_lieu),
    CONSTRAINT fk_hebergement_lieu FOREIGN KEY (id_lieu)
        REFERENCES lieu (id_lieu) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT ck_hebergement_capacite CHECK (capacite > 0),
    CONSTRAINT ck_hebergement_prix     CHECK (prix_nuit >= 0)
) ENGINE=InnoDB;

CREATE TABLE sejour (
    id_sejour      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_lieu        INT UNSIGNED NOT NULL,
    id_hebergement INT UNSIGNED     NULL,
    titre          VARCHAR(150) NOT NULL,
    slug           VARCHAR(170) NOT NULL,
    description    TEXT             NULL,
    format         ENUM('individuel','groupe','evenement') NOT NULL DEFAULT 'individuel',
    duree_jours    SMALLINT UNSIGNED NOT NULL,
    prix_base      DECIMAL(10,2) NOT NULL,
    niveau         ENUM('debutant','intermediaire','avance','tous') NOT NULL DEFAULT 'tous',
    image          VARCHAR(255)     NULL,
    statut         ENUM('brouillon','publie','archive') NOT NULL DEFAULT 'brouillon',
    date_creation  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_sejour),
    UNIQUE KEY uk_sejour_slug (slug),
    KEY idx_sejour_lieu (id_lieu),
    KEY idx_sejour_hebergement (id_hebergement),
    KEY idx_sejour_catalogue (statut, format),
    CONSTRAINT fk_sejour_lieu FOREIGN KEY (id_lieu)
        REFERENCES lieu (id_lieu) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_sejour_hebergement FOREIGN KEY (id_hebergement)
        REFERENCES hebergement (id_hebergement) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ck_sejour_duree CHECK (duree_jours > 0),
    CONSTRAINT ck_sejour_prix  CHECK (prix_base >= 0)
) ENGINE=InnoDB;

CREATE TABLE theme (
    id_theme    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    libelle     VARCHAR(80)  NOT NULL,
    slug        VARCHAR(90)  NOT NULL,
    description VARCHAR(255)     NULL,
    PRIMARY KEY (id_theme),
    UNIQUE KEY uk_theme_slug (slug)
) ENGINE=InnoDB;

-- Association N:N  SEJOUR <-> THEME
CREATE TABLE sejour_theme (
    id_sejour INT UNSIGNED NOT NULL,
    id_theme  INT UNSIGNED NOT NULL,
    PRIMARY KEY (id_sejour, id_theme),
    KEY idx_sejour_theme_theme (id_theme),
    CONSTRAINT fk_sejour_theme_sejour FOREIGN KEY (id_sejour)
        REFERENCES sejour (id_sejour) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_sejour_theme_theme FOREIGN KEY (id_theme)
        REFERENCES theme (id_theme) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE activite (
    id_activite    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    libelle        VARCHAR(120) NOT NULL,
    description    TEXT             NULL,
    duree_minutes  SMALLINT UNSIGNED NULL,
    PRIMARY KEY (id_activite)
) ENGINE=InnoDB;

-- Association N:N porteuse  SEJOUR <-> ACTIVITE (programme du séjour)
CREATE TABLE sejour_activite (
    id_sejour   INT UNSIGNED NOT NULL,
    id_activite INT UNSIGNED NOT NULL,
    jour        SMALLINT UNSIGNED NULL COMMENT 'jour du séjour (1 = J1)',
    ordre       SMALLINT UNSIGNED NULL COMMENT 'ordre dans la journée',
    PRIMARY KEY (id_sejour, id_activite),
    KEY idx_sejour_activite_activite (id_activite),
    CONSTRAINT fk_sejour_activite_sejour FOREIGN KEY (id_sejour)
        REFERENCES sejour (id_sejour) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_sejour_activite_activite FOREIGN KEY (id_activite)
        REFERENCES activite (id_activite) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. SESSIONS (dates de départ) et intervenants
-- ---------------------------------------------------------------------

CREATE TABLE session_sejour (
    id_session       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_sejour        INT UNSIGNED NOT NULL,
    date_debut       DATE         NOT NULL,
    date_fin         DATE         NOT NULL,
    places_totales   SMALLINT UNSIGNED NOT NULL,
    places_reservees SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    prix             DECIMAL(10,2) NOT NULL,
    statut           ENUM('ouverte','complete','annulee') NOT NULL DEFAULT 'ouverte',
    PRIMARY KEY (id_session),
    KEY idx_session_sejour (id_sejour),
    KEY idx_session_date (date_debut),
    CONSTRAINT fk_session_sejour FOREIGN KEY (id_sejour)
        REFERENCES sejour (id_sejour) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ck_session_dates  CHECK (date_fin >= date_debut),
    CONSTRAINT ck_session_places CHECK (places_totales > 0
                                        AND places_reservees <= places_totales),
    CONSTRAINT ck_session_prix   CHECK (prix >= 0)
) ENGINE=InnoDB;

CREATE TABLE intervenant (
    id_intervenant INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom            VARCHAR(80)  NOT NULL,
    prenom         VARCHAR(80)  NOT NULL,
    specialite     VARCHAR(120)     NULL COMMENT 'yoga, sophrologie, méditation…',
    biographie     TEXT             NULL,
    photo          VARCHAR(255)     NULL,
    email          VARCHAR(180)     NULL,
    PRIMARY KEY (id_intervenant)
) ENGINE=InnoDB;

-- Association N:N porteuse  SESSION <-> INTERVENANT
CREATE TABLE session_intervenant (
    id_session     INT UNSIGNED NOT NULL,
    id_intervenant INT UNSIGNED NOT NULL,
    role           VARCHAR(80) NULL COMMENT 'ex. professeur principal, assistant',
    PRIMARY KEY (id_session, id_intervenant),
    KEY idx_session_intervenant_intervenant (id_intervenant),
    CONSTRAINT fk_session_intervenant_session FOREIGN KEY (id_session)
        REFERENCES session_sejour (id_session) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_session_intervenant_intervenant FOREIGN KEY (id_intervenant)
        REFERENCES intervenant (id_intervenant) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. VENTE : réservations, lignes, participants, options, paiements
-- ---------------------------------------------------------------------

CREATE TABLE code_promo (
    id_promo            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code                VARCHAR(30)  NOT NULL,
    type_remise         ENUM('pourcentage','montant') NOT NULL DEFAULT 'pourcentage',
    valeur              DECIMAL(10,2) NOT NULL,
    date_debut          DATE         NOT NULL,
    date_fin            DATE         NOT NULL,
    nb_utilisations_max INT UNSIGNED     NULL,
    nb_utilisations     INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id_promo),
    UNIQUE KEY uk_promo_code (code),
    CONSTRAINT ck_promo_dates  CHECK (date_fin >= date_debut),
    CONSTRAINT ck_promo_valeur CHECK (valeur > 0)
) ENGINE=InnoDB;

CREATE TABLE reservation (
    id_reservation   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_utilisateur   INT UNSIGNED NOT NULL,
    id_promo         INT UNSIGNED     NULL,
    reference        VARCHAR(20)  NOT NULL COMMENT 'ex. ZC-2026-000148',
    date_reservation DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    statut           ENUM('panier','en_attente','confirmee','annulee','terminee')
                     NOT NULL DEFAULT 'panier',
    montant_total    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    montant_remise   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    commentaire      TEXT              NULL,
    PRIMARY KEY (id_reservation),
    UNIQUE KEY uk_reservation_reference (reference),
    KEY idx_reservation_utilisateur (id_utilisateur),
    KEY idx_reservation_promo (id_promo),
    KEY idx_reservation_statut (statut),
    CONSTRAINT fk_reservation_utilisateur FOREIGN KEY (id_utilisateur)
        REFERENCES utilisateur (id_utilisateur) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_reservation_promo FOREIGN KEY (id_promo)
        REFERENCES code_promo (id_promo) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ck_reservation_montants CHECK (montant_total >= 0 AND montant_remise >= 0)
) ENGINE=InnoDB;

CREATE TABLE ligne_reservation (
    id_ligne        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_reservation  INT UNSIGNED NOT NULL,
    id_session      INT UNSIGNED NOT NULL,
    nb_participants SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    prix_unitaire   DECIMAL(10,2) NOT NULL COMMENT 'prix figé au moment de la commande',
    sous_total      DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (id_ligne),
    KEY idx_ligne_reservation (id_reservation),
    KEY idx_ligne_session (id_session),
    CONSTRAINT fk_ligne_reservation FOREIGN KEY (id_reservation)
        REFERENCES reservation (id_reservation) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ligne_session FOREIGN KEY (id_session)
        REFERENCES session_sejour (id_session) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT ck_ligne_nb CHECK (nb_participants > 0)
) ENGINE=InnoDB;

CREATE TABLE participant (
    id_participant     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_ligne           INT UNSIGNED NOT NULL,
    nom                VARCHAR(80)  NOT NULL,
    prenom             VARCHAR(80)  NOT NULL,
    date_naissance     DATE             NULL,
    regime_alimentaire VARCHAR(120)     NULL,
    remarque_sante     VARCHAR(255)     NULL COMMENT 'donnée sensible — RGPD',
    PRIMARY KEY (id_participant),
    KEY idx_participant_ligne (id_ligne),
    CONSTRAINT fk_participant_ligne FOREIGN KEY (id_ligne)
        REFERENCES ligne_reservation (id_ligne) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- `option` est un mot réservé MySQL -> table nommée `option_sejour`
CREATE TABLE option_sejour (
    id_option   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    libelle     VARCHAR(120) NOT NULL COMMENT 'ex. massage, navette, chambre individuelle',
    description VARCHAR(255)     NULL,
    prix        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (id_option),
    CONSTRAINT ck_option_prix CHECK (prix >= 0)
) ENGINE=InnoDB;

-- Association N:N porteuse  LIGNE_RESERVATION <-> OPTION
CREATE TABLE ligne_option (
    id_ligne      INT UNSIGNED NOT NULL,
    id_option     INT UNSIGNED NOT NULL,
    quantite      SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    prix_applique DECIMAL(10,2) NOT NULL COMMENT 'prix figé au moment de la commande',
    PRIMARY KEY (id_ligne, id_option),
    KEY idx_ligne_option_option (id_option),
    CONSTRAINT fk_ligne_option_ligne FOREIGN KEY (id_ligne)
        REFERENCES ligne_reservation (id_ligne) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ligne_option_option FOREIGN KEY (id_option)
        REFERENCES option_sejour (id_option) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT ck_ligne_option_qte CHECK (quantite > 0)
) ENGINE=InnoDB;

CREATE TABLE paiement (
    id_paiement           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_reservation        INT UNSIGNED NOT NULL,
    date_paiement         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    montant               DECIMAL(10,2) NOT NULL,
    moyen_paiement        ENUM('cb','paypal','virement','cheque') NOT NULL DEFAULT 'cb',
    statut                ENUM('en_attente','valide','refuse','rembourse')
                          NOT NULL DEFAULT 'en_attente',
    reference_transaction VARCHAR(100) NULL COMMENT 'identifiant PSP (Stripe…)',
    PRIMARY KEY (id_paiement),
    KEY idx_paiement_reservation (id_reservation),
    CONSTRAINT fk_paiement_reservation FOREIGN KEY (id_reservation)
        REFERENCES reservation (id_reservation) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT ck_paiement_montant CHECK (montant > 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. RELATION CLIENT : avis, messages de contact
-- ---------------------------------------------------------------------

CREATE TABLE avis (
    id_avis        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_utilisateur INT UNSIGNED NOT NULL,
    id_sejour      INT UNSIGNED NOT NULL,
    note           TINYINT UNSIGNED NOT NULL,
    titre          VARCHAR(150)     NULL,
    commentaire    TEXT             NULL,
    date_avis      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    valide         BOOLEAN      NOT NULL DEFAULT FALSE COMMENT 'modération',
    PRIMARY KEY (id_avis),
    UNIQUE KEY uk_avis_utilisateur_sejour (id_utilisateur, id_sejour),  -- RG15
    KEY idx_avis_sejour (id_sejour),
    CONSTRAINT fk_avis_utilisateur FOREIGN KEY (id_utilisateur)
        REFERENCES utilisateur (id_utilisateur) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_avis_sejour FOREIGN KEY (id_sejour)
        REFERENCES sejour (id_sejour) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ck_avis_note CHECK (note BETWEEN 1 AND 5)
) ENGINE=InnoDB;

CREATE TABLE message_contact (
    id_message     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_utilisateur INT UNSIGNED     NULL COMMENT 'NULL si visiteur non connecté',
    nom            VARCHAR(120) NOT NULL,
    email          VARCHAR(180) NOT NULL,
    sujet          VARCHAR(150)     NULL,
    message        TEXT         NOT NULL,
    date_envoi     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    traite         BOOLEAN      NOT NULL DEFAULT FALSE,
    PRIMARY KEY (id_message),
    KEY idx_message_utilisateur (id_utilisateur),
    CONSTRAINT fk_message_utilisateur FOREIGN KEY (id_utilisateur)
        REFERENCES utilisateur (id_utilisateur) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
--  JEU DE DONNÉES DE DÉMONSTRATION
-- =====================================================================

INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, telephone, role) VALUES
('Touzet', 'Raphaël', 'admin@zencamp.fr',  '$2y$10$hashfictifadmin', '0600000001', 'admin'),
('Martin', 'Claire',  'claire@example.fr', '$2y$10$hashfictifuser1', '0600000002', 'client'),
('Dubois', 'Hugo',    'hugo@example.fr',   '$2y$10$hashfictifuser2', '0600000003', 'client');

INSERT INTO lieu (nom, description, ville, code_postal, pays, latitude, longitude) VALUES
('Domaine des Cimes',   'Chalet en pleine nature au cœur du Vercors.', 'Villard-de-Lans', '38250', 'France', 45.070000, 5.550000),
('Bastide du Luberon',  'Mas provençal entouré de lavande.',           'Bonnieux',        '84480', 'France', 43.823000, 5.306000),
('Presqu''île du Silence', 'Site isolé face à l''océan.',              'Crozon',          '29160', 'France', 48.247000, -4.489000);

INSERT INTO hebergement (id_lieu, nom, type_hebergement, capacite, prix_nuit, description) VALUES
(1, 'Cottage Épicéa',   'cottage', 4, 120.00, 'Cottage bois avec poêle et terrasse.'),
(1, 'Yourte Horizon',   'yourte',  2,  85.00, 'Yourte mongole chauffée.'),
(2, 'Chambre Lavande',  'chambre', 2,  95.00, 'Chambre double vue jardin.'),
(3, 'Cabane du Phare',  'cabane',  2, 110.00, 'Cabane isolée sans réseau.');

INSERT INTO theme (libelle, slug, description) VALUES
('Yoga',          'yoga',          'Hatha, vinyasa, yin.'),
('Méditation',    'meditation',    'Pleine conscience et respiration.'),
('Digital detox', 'digital-detox', 'Déconnexion totale des écrans.'),
('Randonnée',     'randonnee',     'Marche consciente en nature.');

INSERT INTO sejour (id_lieu, id_hebergement, titre, slug, description, format, duree_jours, prix_base, niveau, statut) VALUES
(1, 1, 'Retraite Yoga & Montagne',    'retraite-yoga-montagne',  'Cinq jours de yoga face aux falaises du Vercors.', 'groupe',     5, 690.00, 'tous',     'publie'),
(2, 3, 'Méditation en Provence',      'meditation-provence',     'Silence, méditation guidée et cuisine locale.',    'individuel', 3, 450.00, 'debutant', 'publie'),
(3, 4, 'Digital Detox Océan',         'digital-detox-ocean',     'Sept jours sans écran face à l''Atlantique.',      'evenement',  7, 980.00, 'tous',     'publie');

INSERT INTO sejour_theme (id_sejour, id_theme) VALUES
(1, 1), (1, 4),
(2, 1), (2, 2),
(3, 2), (3, 3);

INSERT INTO activite (libelle, description, duree_minutes) VALUES
('Yoga du matin',        'Séance vinyasa au lever du soleil.', 90),
('Méditation guidée',    'Pleine conscience assise.',          45),
('Randonnée silencieuse','Marche consciente en forêt.',       180),
('Atelier respiration',  'Pranayama et cohérence cardiaque.',  60);

INSERT INTO sejour_activite (id_sejour, id_activite, jour, ordre) VALUES
(1, 1, 1, 1), (1, 3, 2, 1),
(2, 2, 1, 1), (2, 4, 2, 1),
(3, 2, 1, 1), (3, 3, 3, 1);

INSERT INTO intervenant (nom, prenom, specialite, email) VALUES
('Lemoine', 'Sarah', 'Professeure de yoga certifiée RYT500', 'sarah@zencamp.fr'),
('Nguyen',  'Thomas','Instructeur de méditation MBSR',       'thomas@zencamp.fr');

INSERT INTO session_sejour (id_sejour, date_debut, date_fin, places_totales, places_reservees, prix, statut) VALUES
(1, '2026-05-11', '2026-05-15', 12, 2, 690.00, 'ouverte'),
(1, '2026-07-06', '2026-07-10', 12, 0, 750.00, 'ouverte'),
(2, '2026-06-01', '2026-06-03',  8, 1, 450.00, 'ouverte'),
(3, '2026-09-14', '2026-09-20', 10, 0, 980.00, 'ouverte');

INSERT INTO session_intervenant (id_session, id_intervenant, role) VALUES
(1, 1, 'professeur principal'),
(2, 1, 'professeur principal'),
(3, 2, 'professeur principal'),
(4, 2, 'professeur principal');

INSERT INTO option_sejour (libelle, description, prix) VALUES
('Massage ayurvédique', 'Séance de 60 minutes.',               75.00),
('Navette gare',        'Aller-retour depuis la gare la plus proche.', 40.00),
('Chambre individuelle','Supplément single pour tout le séjour.',150.00);

INSERT INTO code_promo (code, type_remise, valeur, date_debut, date_fin, nb_utilisations_max, nb_utilisations) VALUES
('ZENWELCOME10', 'pourcentage', 10.00, '2026-01-01', '2026-12-31', 100, 1);

INSERT INTO reservation (id_utilisateur, id_promo, reference, statut, montant_total, montant_remise) VALUES
(2, 1,    'ZC-2026-000148', 'confirmee', 1317.00, 138.00),
(3, NULL, 'ZC-2026-000149', 'en_attente', 450.00,   0.00);

INSERT INTO ligne_reservation (id_reservation, id_session, nb_participants, prix_unitaire, sous_total) VALUES
(1, 1, 2, 690.00, 1380.00),
(2, 3, 1, 450.00,  450.00);

INSERT INTO participant (id_ligne, nom, prenom, date_naissance, regime_alimentaire) VALUES
(1, 'Martin', 'Claire', '1992-04-18', 'végétarien'),
(1, 'Martin', 'Léo',    '1990-11-02', NULL),
(2, 'Dubois', 'Hugo',   '1988-02-27', 'sans gluten');

INSERT INTO ligne_option (id_ligne, id_option, quantite, prix_applique) VALUES
(1, 1, 1, 75.00),
(1, 2, 2, 40.00);

INSERT INTO paiement (id_reservation, montant, moyen_paiement, statut, reference_transaction) VALUES
(1, 500.00, 'cb', 'valide', 'pi_3Nx1acompte'),
(1, 817.00, 'cb', 'valide', 'pi_3Nx1solde');

INSERT INTO avis (id_utilisateur, id_sejour, note, titre, commentaire, valide) VALUES
(2, 1, 5, 'Une parenthèse hors du temps', 'Cadre magnifique et professeure au top.', TRUE);

INSERT INTO message_contact (id_utilisateur, nom, email, sujet, message) VALUES
(NULL, 'Sophie Bernard', 'sophie@example.fr', 'Séjour en famille',
 'Bonjour, proposez-vous des séjours adaptés aux enfants ?');

-- =====================================================================
--  VUES UTILES
-- =====================================================================

-- Catalogue des sessions encore disponibles à la vente
CREATE OR REPLACE VIEW v_sessions_disponibles AS
SELECT  s.id_session,
        se.titre               AS sejour,
        se.format,
        l.ville,
        s.date_debut,
        s.date_fin,
        s.prix,
        (s.places_totales - s.places_reservees) AS places_restantes
FROM    session_sejour s
JOIN    sejour se ON se.id_sejour = s.id_sejour
JOIN    lieu   l  ON l.id_lieu    = se.id_lieu
WHERE   s.statut = 'ouverte'
  AND   se.statut = 'publie'
  AND   s.places_reservees < s.places_totales
  AND   s.date_debut >= CURDATE();

-- Note moyenne par séjour (avis modérés uniquement)
CREATE OR REPLACE VIEW v_note_moyenne_sejour AS
SELECT  se.id_sejour,
        se.titre,
        ROUND(AVG(a.note), 2) AS note_moyenne,
        COUNT(a.id_avis)      AS nb_avis
FROM    sejour se
LEFT JOIN avis a ON a.id_sejour = se.id_sejour AND a.valide = TRUE
GROUP BY se.id_sejour, se.titre;

-- Chiffre d'affaires encaissé par séjour
CREATE OR REPLACE VIEW v_ca_par_sejour AS
SELECT  se.id_sejour,
        se.titre,
        SUM(lr.sous_total) AS ca_ttc,
        COUNT(DISTINCT r.id_reservation) AS nb_reservations
FROM    sejour se
JOIN    session_sejour s     ON s.id_sejour      = se.id_sejour
JOIN    ligne_reservation lr ON lr.id_session    = s.id_session
JOIN    reservation r        ON r.id_reservation = lr.id_reservation
WHERE   r.statut IN ('confirmee','terminee')
GROUP BY se.id_sejour, se.titre;
