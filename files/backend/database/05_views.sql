
SET NAMES utf8mb4;

-- 1. Vue catalogue / état opérationnel des équipements
-- Agrège la catégorie, les incidents actifs/bloquants et les réservations actives à venir.
CREATE OR REPLACE VIEW v_equipements_catalogue AS
    SELECT
        e.id_equipement,
        e.nom,
        e.statut_technique,
        e.localisation,
        e.description,
        e.visible_public,
        e.controle_requis,
        e.id_categorie,
        c.libelle AS categorie_libelle,
        COALESCE(inc.incidents_actifs, 0) AS incidents_actifs,
        COALESCE(inc.incidents_bloquants_actifs, 0) AS incidents_bloquants_actifs,
        CASE
            WHEN COALESCE(inc.incidents_bloquants_actifs, 0) > 0 THEN 1
            ELSE 0
        END AS a_panne_bloquante,
        COALESCE(res.reservations_a_venir, 0) AS reservations_a_venir
    FROM EQUIPEMENT e
    INNER JOIN CATEGORIE_EQUIPEMENT c
        ON c.id_categorie = e.id_categorie
    LEFT JOIN (
        SELECT
            id_equipement,
            COUNT(*) AS incidents_actifs,
            SUM(CASE WHEN est_bloquant = 1 THEN 1 ELSE 0 END) AS incidents_bloquants_actifs
        FROM INCIDENT
        WHERE statut IN ('OUVERT', 'EN_COURS')
        GROUP BY id_equipement
    ) inc
        ON inc.id_equipement = e.id_equipement
    LEFT JOIN (
        SELECT
            id_equipement,
            COUNT(*) AS reservations_a_venir
        FROM RESERVATION
        WHERE statut IN ('DEMANDEE', 'CONFIRMEE')
            AND date_fin >= NOW()
        GROUP BY id_equipement
    ) res
        ON res.id_equipement = e.id_equipement;

-- 2. Vue détaillée de suivi des incidents
-- Joint l'équipement et l'utilisateur déclarant, avec compteurs d'historique et d'interventions.
CREATE OR REPLACE VIEW v_incidents_suivi AS
    SELECT
        i.id_incident,
        i.titre,
        i.description,
        i.date_signalement,
        i.date_resolution,
        i.statut,
        i.est_technique,
        i.est_bloquant,
        i.id_equipement,
        e.nom AS equipement_nom,
        e.localisation AS equipement_localisation,
        e.statut_technique AS equipement_statut_technique,
        i.id_utilisateur AS auteur_id,
        u.nom AS auteur_nom,
        u.role AS auteur_role,
        COALESCE(hist.nb_evenements_historique, 0) AS nb_evenements_historique,
        COALESCE(interv.nb_interventions, 0) AS nb_interventions,
        COALESCE(interv.interventions_en_cours, 0) AS interventions_en_cours
    FROM INCIDENT i
    INNER JOIN EQUIPEMENT e
        ON e.id_equipement = i.id_equipement
    INNER JOIN UTILISATEUR u
        ON u.id_utilisateur = i.id_utilisateur
    LEFT JOIN (
        SELECT
            id_incident,
            COUNT(*) AS nb_evenements_historique
        FROM HISTORIQUE_INCIDENT
        GROUP BY id_incident
    ) hist
        ON hist.id_incident = i.id_incident
    LEFT JOIN (
        SELECT
            id_incident,
            COUNT(*) AS nb_interventions,
            SUM(CASE WHEN etat = 'EN_COURS' THEN 1 ELSE 0 END) AS interventions_en_cours
        FROM INTERVENTION
        GROUP BY id_incident
    ) interv
        ON interv.id_incident = i.id_incident;