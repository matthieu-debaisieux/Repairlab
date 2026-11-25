-- RepairLab : jeu d'essai (données fictives)
-- Mot de passe de tous les comptes de démonstration : repairlab2026 (hash bcrypt généré avec password_hash).
-- Les dates sont relatives à la date d'exécution, pour que les scénarios restent valides (fenêtre de 30 jours de R8).
-- Aucune donnée personnelle réelle : les noms et adresses sont inventés.

SET NAMES utf8mb4;

INSERT INTO CATEGORIE_EQUIPEMENT (id_categorie, libelle) VALUES
(1, 'Impression 3D'),
(2, 'Découpe'),
(3, 'Soudure'),
(4, 'Informatique'),
(5, 'Outillage');

INSERT INTO UTILISATEUR (id_utilisateur, nom, email, mot_de_passe_hash, role) VALUES
(1, 'Alice Martin',  'alice.martin@fabrique.example',  '$2y$12$nEJe6NNKYLl5yoKEAyzpWeibpHnAmwVF.PyMHytXQbwXIH.cloDHC', 'ADMIN'),
(2, 'Karim Benali',  'karim.benali@fabrique.example',  '$2y$12$nEJe6NNKYLl5yoKEAyzpWeibpHnAmwVF.PyMHytXQbwXIH.cloDHC', 'TECHNICIEN'),
(3, 'Camille Durand','camille.durand@fabrique.example','$2y$12$nEJe6NNKYLl5yoKEAyzpWeibpHnAmwVF.PyMHytXQbwXIH.cloDHC', 'ADHERENT'),
(4, 'Lucas Moreau',  'lucas.moreau@fabrique.example',  '$2y$12$nEJe6NNKYLl5yoKEAyzpWeibpHnAmwVF.PyMHytXQbwXIH.cloDHC', 'ADHERENT'),
(5, 'Inès Petit',    'ines.petit@fabrique.example',    '$2y$12$nEJe6NNKYLl5yoKEAyzpWeibpHnAmwVF.PyMHytXQbwXIH.cloDHC', 'ADHERENT'),
(6, 'Hugo Roux',     'hugo.roux@fabrique.example',     '$2y$12$nEJe6NNKYLl5yoKEAyzpWeibpHnAmwVF.PyMHytXQbwXIH.cloDHC', 'ADHERENT');

-- Équipement 2 : hors service (incident bloquant, scénario R3).
-- Équipement 6 : non visible dans le catalogue public.
INSERT INTO EQUIPEMENT (id_equipement, nom, statut_technique, localisation, description, visible_public, controle_requis, id_categorie) VALUES
(1, 'Imprimante 3D Prusa MK4',  'EN_SERVICE',  'Atelier A',        'Imprimante 3D FDM, plateau 25 x 21 cm.', TRUE,  FALSE, 1),
(2, 'Imprimante 3D Bambu P1S',  'HORS_SERVICE','Atelier A',        'Imprimante 3D fermée, multi-matériaux.', TRUE,  FALSE, 1),
(3, 'Découpeuse laser',         'EN_SERVICE',  'Atelier B',        'Découpe et gravure, bois et acrylique.', TRUE,  FALSE, 2),
(4, 'Poste de soudure n°1',     'EN_SERVICE',  'Atelier C',        'Poste à l''étain pour électronique.',    TRUE,  FALSE, 3),
(5, 'PC de conception n°2',     'EN_SERVICE',  'Espace numérique', 'Poste de modélisation et de découpe.',   TRUE,  FALSE, 4),
(6, 'Perceuse à colonne',       'EN_SERVICE',  'Atelier C',        'Réservée à un usage encadré.',           FALSE, FALSE, 5);

-- Scénario R9 : la découpeuse est en maintenance du lendemain à J+3.
INSERT INTO MAINTENANCE (id_maintenance, date_debut, date_fin, motif, id_equipement) VALUES
(1, TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 1 DAY), '08:00:00'), TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 3 DAY), '18:00:00'), 'Remplacement du tube laser', 3);

-- Contrôle ancien : ne neutralise pas les incidents récents de la Bambu (scénario R8).
INSERT INTO CONTROLE (id_controle, date_controle, compte_rendu, id_utilisateur, id_equipement) VALUES
(1, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 40 DAY), '10:00:00'), 'Contrôle périodique : aucune anomalie.', 2, 2);

-- Scénario R1 : créneaux réservés aux ateliers encadrés.
INSERT INTO CRENEAU_PRIORITAIRE (id_creneau, date_debut, date_fin, libelle_atelier, id_equipement) VALUES
(1, TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 5 DAY), '17:30:00'), TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 5 DAY), '20:30:00'), 'Atelier initiation à l''impression 3D', 1),
(2, TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 6 DAY), '14:00:00'), TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 6 DAY), '17:00:00'), 'Atelier découpe laser', 3);

-- 1 : réservation confirmée sur la Prusa, demain.
-- 2 : demande d'un adhérent sur un créneau prioritaire (reste DEMANDEE, scénario R1).
-- 3 et 4 : passées (terminée, annulée).
-- 5 : réservation conservée sur la machine en panne, marquée « à vérifier » (scénario R3).
INSERT INTO RESERVATION (id_reservation, date_debut, date_fin, statut, a_verifier, id_utilisateur, id_equipement, id_creneau) VALUES
(1, TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 1 DAY), '14:00:00'), TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 1 DAY), '16:00:00'), 'CONFIRMEE', FALSE, 3, 1, NULL),
(2, TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 5 DAY), '18:00:00'), TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 5 DAY), '20:00:00'), 'DEMANDEE',  FALSE, 4, 1, 1),
(3, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '09:00:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '11:00:00'), 'TERMINEE',  FALSE, 5, 5, NULL),
(4, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '15:00:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '17:00:00'), 'ANNULEE',   FALSE, 6, 4, NULL),
(5, TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 3 DAY), '10:00:00'), TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 3 DAY), '12:00:00'), 'CONFIRMEE', TRUE,  3, 2, NULL);

-- Incident 1 : ancien, clôturé. Incident 2 : bloquant, en cours.
-- La Bambu cumule ainsi deux incidents techniques en 30 jours : un troisième déclenche CONTROLE_REQUIS (scénario R8).
INSERT INTO INCIDENT (id_incident, titre, description, date_signalement, date_resolution, statut, est_technique, est_bloquant, id_equipement, id_utilisateur) VALUES
(1, 'Câble d''alimentation défectueux', 'La machine s''éteint en cours d''impression.', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 20 DAY), '09:00:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 18 DAY), '17:00:00'), 'CLOTURE',  TRUE, FALSE, 2, 5),
(2, 'Buse bouchée', 'Le filament ne sort plus de la buse.', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '11:00:00'), NULL, 'EN_COURS', TRUE, TRUE, 2, 5);

-- Historique saisi à la main ; une fois le trigger créé, les changements d'état futurs seront historisés automatiquement.
INSERT INTO HISTORIQUE_INCIDENT (id_historique, ancien_etat, nouvel_etat, date_changement, motif, id_incident, id_utilisateur) VALUES
(1, NULL,       'OUVERT',   TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 20 DAY), '09:00:00'), 'Signalement',     1, 5),
(2, 'OUVERT',   'EN_COURS', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 19 DAY), '10:00:00'), 'Prise en charge', 1, 2),
(3, 'EN_COURS', 'RESOLU',   TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 18 DAY), '16:00:00'), 'Câble remplacé',  1, 2),
(4, 'RESOLU',   'CLOTURE',  TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 18 DAY), '17:00:00'), 'Vérifié',         1, 2),
(5, NULL,       'OUVERT',   TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY),  '11:00:00'), 'Signalement',     2, 5),
(6, 'OUVERT',   'EN_COURS', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY),  '14:00:00'), 'Prise en charge', 2, 2);

-- Intervention 1 : dossier complet. Intervention 2 : dossier incomplet (scénario R10 : remise en service refusée).
INSERT INTO INTERVENTION (id_intervention, etat, diagnostic, action, resultat_test, date_debut, date_fin, id_incident) VALUES
(1, 'TERMINEE', 'Câble d''alimentation sectionné.', 'Câble remplacé.', 'Impression de test de 30 minutes réussie.', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 19 DAY), '10:00:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 18 DAY), '16:00:00'), 1),
(2, 'EN_COURS', 'Buse partiellement obstruée.', NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '14:00:00'), NULL, 2);

INSERT INTO PARTICIPER (id_utilisateur, id_intervention) VALUES
(2, 1),
(2, 2);