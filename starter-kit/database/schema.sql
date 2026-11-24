SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS PARTICIPER;
DROP TABLE IF EXISTS HISTORIQUE_INCIDENT;
DROP TABLE IF EXISTS INTERVENTION;
DROP TABLE IF EXISTS INCIDENT;
DROP TABLE IF EXISTS RESERVATION;
DROP TABLE IF EXISTS CRENEAU_PRIORITAIRE;
DROP TABLE IF EXISTS CONTROLE;
DROP TABLE IF EXISTS MAINTENANCE;
DROP TABLE IF EXISTS EQUIPEMENT;
DROP TABLE IF EXISTS UTILISATEUR;
DROP TABLE IF EXISTS CATEGORIE_EQUIPEMENT;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------
-- Tables sans dépendance
-- ---------------------------------------------------------------

CREATE TABLE CATEGORIE_EQUIPEMENT (
    id_categorie INT UNSIGNED NOT NULL AUTO_INCREMENT,
    libelle VARCHAR(100) NOT NULL,
    PRIMARY KEY (id_categorie),
    UNIQUE KEY uq_categorie_libelle (libelle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE UTILISATEUR (
    id_utilisateur INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL,
    mot_de_passe_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'ADHERENT',
    PRIMARY KEY (id_utilisateur),
    UNIQUE KEY uq_utilisateur_email (email),
    CONSTRAINT ck_utilisateur_role CHECK (role IN ('ADMIN', 'TECHNICIEN', 'ADHERENT'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Équipements
-- ---------------------------------------------------------------

CREATE TABLE EQUIPEMENT (
    id_equipement INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    statut_technique VARCHAR(20) NOT NULL DEFAULT 'EN_SERVICE',
    localisation VARCHAR(100) NULL,
    description TEXT NULL,
    visible_public BOOLEAN NOT NULL DEFAULT TRUE,
    id_categorie INT UNSIGNED NOT NULL,
    PRIMARY KEY (id_equipement),
    KEY idx_equipement_categorie (id_categorie),
    CONSTRAINT fk_equipement_categorie FOREIGN KEY (id_categorie)
        REFERENCES CATEGORIE_EQUIPEMENT (id_categorie) ON DELETE RESTRICT,
    CONSTRAINT ck_equipement_statut CHECK (statut_technique IN ('EN_SERVICE', 'HORS_SERVICE'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE MAINTENANCE (
    id_maintenance INT UNSIGNED NOT NULL AUTO_INCREMENT,
    date_debut DATETIME NOT NULL,
    date_fin DATETIME NOT NULL,
    motif VARCHAR(255) NULL,
    id_equipement INT UNSIGNED NOT NULL,
    PRIMARY KEY (id_maintenance),
    KEY idx_maintenance_equipement_dates (id_equipement, date_debut, date_fin),
    CONSTRAINT fk_maintenance_equipement FOREIGN KEY (id_equipement)
        REFERENCES EQUIPEMENT (id_equipement) ON DELETE RESTRICT,
    CONSTRAINT ck_maintenance_dates CHECK (date_debut < date_fin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE CONTROLE (
    id_controle INT UNSIGNED NOT NULL AUTO_INCREMENT,
    date_controle DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    compte_rendu TEXT NOT NULL,
    id_utilisateur INT UNSIGNED NOT NULL,
    id_equipement INT UNSIGNED NOT NULL,
    PRIMARY KEY (id_controle),
    KEY idx_controle_equipement_date (id_equipement, date_controle),
    KEY idx_controle_utilisateur (id_utilisateur),
    CONSTRAINT fk_controle_utilisateur FOREIGN KEY (id_utilisateur)
        REFERENCES UTILISATEUR (id_utilisateur) ON DELETE RESTRICT,
    CONSTRAINT fk_controle_equipement FOREIGN KEY (id_equipement)
        REFERENCES EQUIPEMENT (id_equipement) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Un créneau prioritaire existe indépendamment de toute réservation.
CREATE TABLE CRENEAU_PRIORITAIRE (
    id_creneau INT UNSIGNED NOT NULL AUTO_INCREMENT,
    date_debut DATETIME NOT NULL,
    date_fin DATETIME NOT NULL,
    libelle_atelier VARCHAR(150) NOT NULL,
    id_equipement INT UNSIGNED NOT NULL,
    PRIMARY KEY (id_creneau),
    KEY idx_creneau_equipement_dates (id_equipement, date_debut, date_fin),
    CONSTRAINT fk_creneau_equipement FOREIGN KEY (id_equipement)
        REFERENCES EQUIPEMENT (id_equipement) ON DELETE RESTRICT,
    CONSTRAINT ck_creneau_dates CHECK (date_debut < date_fin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Réservations (association « affecter » : clé étrangère côté 0,1, donc nullable)
-- ---------------------------------------------------------------

CREATE TABLE RESERVATION (
    id_reservation INT UNSIGNED NOT NULL AUTO_INCREMENT,
    date_debut DATETIME NOT NULL,
    date_fin DATETIME NOT NULL,
    statut VARCHAR(20) NOT NULL DEFAULT 'DEMANDEE',
    id_utilisateur INT UNSIGNED NOT NULL,
    id_equipement INT UNSIGNED NOT NULL,
    id_creneau INT UNSIGNED NULL,
    PRIMARY KEY (id_reservation),
    KEY idx_reservation_equipement_dates (id_equipement, date_debut, date_fin),
    KEY idx_reservation_utilisateur (id_utilisateur),
    KEY idx_reservation_creneau (id_creneau),
    CONSTRAINT fk_reservation_utilisateur FOREIGN KEY (id_utilisateur)
        REFERENCES UTILISATEUR (id_utilisateur) ON DELETE RESTRICT,
    CONSTRAINT fk_reservation_equipement FOREIGN KEY (id_equipement)
        REFERENCES EQUIPEMENT (id_equipement) ON DELETE RESTRICT,
    CONSTRAINT fk_reservation_creneau FOREIGN KEY (id_creneau)
        REFERENCES CRENEAU_PRIORITAIRE (id_creneau) ON DELETE SET NULL,
    CONSTRAINT ck_reservation_dates CHECK (date_debut < date_fin),
    CONSTRAINT ck_reservation_statut CHECK (statut IN ('DEMANDEE', 'CONFIRMEE', 'ANNULEE', 'TERMINEE'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Incidents, historique et interventions
-- ---------------------------------------------------------------

CREATE TABLE INCIDENT (
    id_incident INT UNSIGNED NOT NULL AUTO_INCREMENT,
    titre VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    date_signalement DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_resolution DATETIME NULL,
    statut VARCHAR(20) NOT NULL DEFAULT 'OUVERT',
    est_technique BOOLEAN NOT NULL DEFAULT TRUE,
    est_bloquant BOOLEAN NOT NULL DEFAULT FALSE,
    id_equipement INT UNSIGNED NOT NULL,
    id_utilisateur INT UNSIGNED NOT NULL,
    PRIMARY KEY (id_incident),
    KEY idx_incident_equipement (id_equipement, statut, date_signalement),
    KEY idx_incident_utilisateur (id_utilisateur),
    CONSTRAINT fk_incident_equipement FOREIGN KEY (id_equipement)
        REFERENCES EQUIPEMENT (id_equipement) ON DELETE RESTRICT,
    CONSTRAINT fk_incident_utilisateur FOREIGN KEY (id_utilisateur)
        REFERENCES UTILISATEUR (id_utilisateur) ON DELETE RESTRICT,
    CONSTRAINT ck_incident_statut CHECK (statut IN ('OUVERT', 'EN_COURS', 'RESOLU', 'CLOTURE')),
    CONSTRAINT ck_incident_dates CHECK (date_resolution IS NULL OR date_resolution >= date_signalement)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ancien_etat est NULL pour la toute première ligne (création de l'incident).
CREATE TABLE HISTORIQUE_INCIDENT (
    id_historique INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ancien_etat VARCHAR(20) NULL,
    nouvel_etat VARCHAR(20) NOT NULL,
    date_changement DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    motif VARCHAR(255) NULL,
    id_incident INT UNSIGNED NOT NULL,
    id_utilisateur INT UNSIGNED NOT NULL,
    PRIMARY KEY (id_historique),
    KEY idx_historique_incident_date (id_incident, date_changement),
    KEY idx_historique_utilisateur (id_utilisateur),
    CONSTRAINT fk_historique_incident FOREIGN KEY (id_incident)
        REFERENCES INCIDENT (id_incident) ON DELETE RESTRICT,
    CONSTRAINT fk_historique_utilisateur FOREIGN KEY (id_utilisateur)
        REFERENCES UTILISATEUR (id_utilisateur) ON DELETE RESTRICT,
    CONSTRAINT ck_historique_ancien CHECK (ancien_etat IS NULL OR ancien_etat IN ('OUVERT', 'EN_COURS', 'RESOLU', 'CLOTURE')),
    CONSTRAINT ck_historique_nouvel CHECK (nouvel_etat IN ('OUVERT', 'EN_COURS', 'RESOLU', 'CLOTURE'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- diagnostic, action et resultat_test restent NULL tant que le dossier n'est pas rempli (R10).
CREATE TABLE INTERVENTION (
    id_intervention INT UNSIGNED NOT NULL AUTO_INCREMENT,
    etat VARCHAR(20) NOT NULL DEFAULT 'EN_COURS',
    diagnostic TEXT NULL,
    action TEXT NULL,
    resultat_test TEXT NULL,
    date_debut DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_fin DATETIME NULL,
    id_incident INT UNSIGNED NOT NULL,
    PRIMARY KEY (id_intervention),
    KEY idx_intervention_incident (id_incident),
    CONSTRAINT fk_intervention_incident FOREIGN KEY (id_incident)
        REFERENCES INCIDENT (id_incident) ON DELETE RESTRICT,
    CONSTRAINT ck_intervention_etat CHECK (etat IN ('EN_COURS', 'TERMINEE')),
    CONSTRAINT ck_intervention_dates CHECK (date_fin IS NULL OR date_fin >= date_debut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Association N-N entre techniciens et interventions.
CREATE TABLE PARTICIPER (
    id_utilisateur INT UNSIGNED NOT NULL,
    id_intervention INT UNSIGNED NOT NULL,
    PRIMARY KEY (id_utilisateur, id_intervention),
    KEY idx_participer_intervention (id_intervention),
    CONSTRAINT fk_participer_utilisateur FOREIGN KEY (id_utilisateur)
        REFERENCES UTILISATEUR (id_utilisateur) ON DELETE RESTRICT,
    CONSTRAINT fk_participer_intervention FOREIGN KEY (id_intervention)
        REFERENCES INTERVENTION (id_intervention) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
