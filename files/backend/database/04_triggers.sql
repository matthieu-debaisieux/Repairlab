-- Triggers de traçabilité des incidents (R6).
-- À exécuter après 01_schema.sql, 02_seed.sql et 03_procedures.sql.
--
-- Avant un UPDATE, l'application pose deux variables de session MySQL :
--   @app_user_id        identifiant de l'utilisateur qui agit
--   @motif_changement   motif saisi (obligatoire pour une réouverture)
-- Si @app_user_id est absent (modification faite à la main en SQL), on retombe sur l'auteur de l'incident.

DROP TRIGGER IF EXISTS trg_incident_apres_insertion;
DROP TRIGGER IF EXISTS trg_incident_apres_modification;

DELIMITER //

CREATE TRIGGER trg_incident_apres_insertion
AFTER INSERT ON INCIDENT
FOR EACH ROW
BEGIN
    INSERT INTO HISTORIQUE_INCIDENT
        (ancien_etat, nouvel_etat, date_changement, motif, id_incident, id_utilisateur)
    VALUES
        (NULL, NEW.statut, NOW(), 'Signalement', NEW.id_incident, NEW.id_utilisateur);
END//

CREATE TRIGGER trg_incident_apres_modification
AFTER UPDATE ON INCIDENT
FOR EACH ROW
BEGIN
    IF NOT (OLD.statut <=> NEW.statut) THEN
        INSERT INTO HISTORIQUE_INCIDENT
            (ancien_etat, nouvel_etat, date_changement, motif, id_incident, id_utilisateur)
        VALUES
            (OLD.statut, NEW.statut, NOW(), @motif_changement, NEW.id_incident,
            COALESCE(@app_user_id, NEW.id_utilisateur));
    END IF;
END//

DELIMITER ;