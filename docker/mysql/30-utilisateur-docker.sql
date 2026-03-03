-- =====================================================================
--  ZenCamp — Comptes MySQL adaptés à Docker
--
--  zencamp_securite.sql crée « zencamp_app'@'localhost », ce qui convient
--  à une installation classique où PHP et MySQL partagent la machine.
--  En conteneurs, PHP se connecte depuis un AUTRE hôte du réseau Docker :
--  il faut donc un compte accessible depuis ce réseau.
--
--  Les privilèges restent identiques : SELECT, INSERT, UPDATE, DELETE.
--  Ni DROP, ni ALTER, ni CREATE, ni FILE — une injection qui passerait
--  malgré tout ne peut ni détruire le schéma ni écrire sur le disque.
-- =====================================================================

CREATE USER IF NOT EXISTS 'zencamp_app'@'%'
    IDENTIFIED BY 'mot_de_passe_developpement';

GRANT SELECT, INSERT, UPDATE, DELETE ON zencamp.* TO 'zencamp_app'@'%';

-- Compte de consultation, limité aux vues métier.
CREATE USER IF NOT EXISTS 'zencamp_stats'@'%'
    IDENTIFIED BY 'mot_de_passe_developpement';

GRANT SELECT ON zencamp.v_sessions_disponibles TO 'zencamp_stats'@'%';
GRANT SELECT ON zencamp.v_note_moyenne_sejour  TO 'zencamp_stats'@'%';
GRANT SELECT ON zencamp.v_ca_par_sejour        TO 'zencamp_stats'@'%';

FLUSH PRIVILEGES;

-- Ce mot de passe est un mot de passe de DÉVELOPPEMENT, valable
-- uniquement dans le réseau interne des conteneurs. En production, il
-- est remplacé par un secret injecté à l'exécution et jamais versionné.
