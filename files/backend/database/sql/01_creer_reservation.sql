DELIMITER //

CREATE PROCEDURE sp_creer_reservation (
    IN  p_id_equipement  INT UNSIGNED,
    IN  p_id_utilisateur INT UNSIGNED,
    IN  p_date_debut     DATETIME,
    IN  p_date_fin       DATETIME,
    IN  p_statut_initial VARCHAR(20),
    IN  p_id_creneau     INT UNSIGNED,
    OUT p_resultat       VARCHAR(50),
    OUT p_id_reservation INT UNSIGNED
)

BEGIN
    DECLARE v_nb_conflits  INT UNSIGNED DEFAULT 0;
    DECLARE v_nb_bloquants INT UNSIGNED DEFAULT 0;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_id_reservation = NULL;
        SET p_resultat = 'ERREUR_SQL';
    END;

    SET p_id_reservation = NULL;

    -- 1. Cohérence des bornes temporelles (invariant du socle)
    IF p_date_debut IS NULL OR p_date_fin IS NULL OR p_date_debut >= p_date_fin THEN
        SET p_resultat = 'DATES_INVALIDES';
    ELSEIF p_statut_initial NOT IN ('DEMANDEE', 'CONFIRMEE') THEN
        SET p_resultat = 'STATUT_INVALIDE';
    ELSE
        START TRANSACTION;

        -- 2. Verrouillage et détection de chevauchement sur réservations actives
        SELECT COUNT(*)
        INTO v_nb_conflits
        FROM RESERVATION
        WHERE id_equipement = p_id_equipement
            AND statut IN ('DEMANDEE', 'CONFIRMEE')
            AND date_debut < p_date_fin
            AND date_fin   > p_date_debut
        FOR UPDATE;

        -- 3. Vérification incident bloquant ouvert (Règle R3 : OUVERT ou EN_COURS)
        SELECT COUNT(*)
        INTO v_nb_bloquants
        FROM INCIDENT
        WHERE id_equipement = p_id_equipement
            AND est_bloquant = TRUE
            AND statut IN ('OUVERT', 'EN_COURS')
        FOR UPDATE;

        IF v_nb_bloquants > 0 THEN
            ROLLBACK;
            SET p_resultat = 'INCIDENT_BLOQUANT';
        ELSEIF v_nb_conflits > 0 THEN
            ROLLBACK;
            SET p_resultat = 'CHEVAUCHEMENT';
        ELSE
            INSERT INTO RESERVATION (
                date_debut,
                date_fin,
                statut,
                a_verifier,
                id_utilisateur,
                id_equipement,
                id_creneau
            )
            VALUES (
                p_date_debut,
                p_date_fin,
                p_statut_initial,
                FALSE,
                p_id_utilisateur,
                p_id_equipement,
                p_id_creneau
            );

            SET p_id_reservation = LAST_INSERT_ID();
            COMMIT;
            SET p_resultat = 'OK';
        END IF;
    END IF;
END//
DELIMITER ;