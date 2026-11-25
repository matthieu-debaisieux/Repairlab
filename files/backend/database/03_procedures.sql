DROP PROCEDURE IF EXISTS sp_creer_reservation;

DELIMITER //

CREATE PROCEDURE sp_creer_reservation(
    IN  p_utilisateur    INT UNSIGNED,
    IN  p_equipement     INT UNSIGNED,
    IN  p_debut          DATETIME,
    IN  p_fin            DATETIME,
    IN  p_statut_initial VARCHAR(20),
    IN  p_id_creneau     INT UNSIGNED,
    OUT p_id_reservation INT UNSIGNED
)
BEGIN
    DECLARE v_existe          INT DEFAULT 0;
    DECLARE v_conflits        INT DEFAULT 0;
    DECLARE v_panne_bloquante INT DEFAULT 0;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    IF p_debut IS NULL OR p_fin IS NULL OR p_debut >= p_fin THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DATES_INVALIDES';
    END IF;

    IF p_debut < NOW() THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DATE_PASSEE';
    END IF;

    IF p_statut_initial NOT IN ('DEMANDEE', 'CONFIRMEE') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'STATUT_INVALIDE';
    END IF;

    START TRANSACTION;

    SELECT COUNT(*) INTO v_existe
    FROM EQUIPEMENT
    WHERE id_equipement = p_equipement
    FOR UPDATE;

    IF v_existe = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'EQUIPEMENT_INCONNU';
    END IF;

    SELECT COUNT(*) INTO v_panne_bloquante
    FROM INCIDENT
    WHERE id_equipement = p_equipement
        AND est_bloquant = 1
        AND statut IN ('OUVERT', 'EN_COURS')
    FOR UPDATE;

    IF v_panne_bloquante > 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'PANNE_BLOQUANTE';
    END IF;

    SELECT COUNT(*) INTO v_conflits
    FROM RESERVATION
    WHERE id_equipement = p_equipement
        AND statut IN ('DEMANDEE', 'CONFIRMEE')
        AND date_debut < p_fin
        AND date_fin   > p_debut
    FOR UPDATE;

    IF v_conflits > 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'CRENEAU_OCCUPE';
    END IF;

    INSERT INTO RESERVATION (
        date_debut,
        date_fin,
        statut,
        id_utilisateur,
        id_equipement,
        id_creneau
    )
    VALUES (
        p_debut,
        p_fin,
        p_statut_initial,
        p_utilisateur,
        p_equipement,
        p_id_creneau
    );

    SET p_id_reservation = LAST_INSERT_ID();

    COMMIT;
END//

DELIMITER ;