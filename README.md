# RepairLab

Application web de gestion d'atelier partagé (fablab) : catalogue d'équipements, réservations avec gestion des créneaux prioritaires, suivi des incidents et interventions, et tableau de bord technicien.

## Règles métier retenues

| Règle | Type | Principe |
| --- | --- | --- |
| R1 — Réservation prioritaire | Contractuelle | Certains créneaux sont réservés aux ateliers encadrés : un adhérent obtient une demande (`DEMANDEE`), un technicien confirme. Évolution `EV-MAT-01` (`ATELIER_PRIORITAIRE`). |
| R3 — Panne bloquante | Contractuelle | Un incident bloquant interdit les nouvelles réservations (`409 PANNE_BLOQUANTE`) et signale les réservations futures existantes (`a_verifier`). |
| R6 — Procédure d'un incident | Contractuelle | Cycle de vie strict `OUVERT → EN_COURS → RESOLU → CLOTURE`, retour arrière tracé avec motif obligatoire. |
| R8 — Trois pannes en trente jours | Extension | Indicateur `CONTROLE_REQUIS` calculé automatiquement. |
| R9 — Disponibilité expliquée | Extension | Statut précis avec la raison de disponibilité/indisponibilité. |
| R10 — Validation qualité | Extension | Dossier d'intervention complet exigé avant la remise en service d'un équipement. |

## Technologies

* **Backend :** PHP 8.5 (compatible PHP 8.2+), Slim 4, Medoo
* **Base de données :** MySQL 8 (compatible MySQL 5.7.24+, moteur `InnoDB`, encodage `utf8mb4`)
* **Frontend :** Templates PHP / HTML / CSS avec protection CSRF et échappement XSS
* **Tests & Qualité :** Composer (`composer test:db`)

## Structure du dépôt

* `database/` — Scripts SQL à exécuter impérativement dans l'ordre numérique :
  * `01_schema.sql` : création des tables, clés étrangères et contraintes d'intégrité
  * `02_seed.sql` : jeu de données initial et comptes de démonstration
  * `03_procedures.sql` : procédures stockées (création de réservation, contrôle de chevauchement)
  * `04_triggers.sql` : déclencheurs SQL (traçabilité automatique dans `HISTORIQUE_INCIDENT`)
  * `05_views.sql` : vues SQL exploitées par les repositories (`v_equipements_catalogue`, `v_incidents_suivi`)
* `src/` — Code applicatif PHP (`Domain`, `Repository`, `Service`, `Middleware`, `Controller`)
* `routes/` — Définition des routes web et API JSON
* `templates/` — Gabarits PHP (`home.php`, `mon-espace.php`, `technicien.php`, fiches équipements et incidents)
* `tests/` — Scripts de vérification automatisés et tests de recette
* `documentation/` — Cahier des charges, MCD/MLD, contrat API et notes d'évolution (`evolution-EV-MAT-01.md`)

## Installation et reconstruction de la base

Prérequis : PHP 8.2+ avec l'extension `pdo_mysql`, Composer et un serveur MySQL (5.7.24+ ou 8.0+).

1. **Reconstruire la base de données neuve** en exécutant les 5 scripts SQL dans l'ordre strict :

```bash
mysql -u <utilisateur> -p <nom_base> < database/01_schema.sql
mysql -u <utilisateur> -p <nom_base> < database/02_seed.sql
mysql -u <utilisateur> -p <nom_base> < database/03_procedures.sql
mysql -u <utilisateur> -p <nom_base> < database/04_triggers.sql
mysql -u <utilisateur> -p <nom_base> < database/05_views.sql
```

*(Note : les scripts sont rejouables sur une base existante ; `01_schema.sql` réinitialise les tables et `05_views.sql` utilise `CREATE OR REPLACE VIEW`.)*

2. **Installer les dépendances PHP** :

```bash
composer install
```

3. **Vérifier l'installation et lancer la suite de tests** :

```bash
composer test:db
```

## Comptes de démonstration

| E-mail | Rôle |
| --- | --- |
| `alice.martin@fabrique.example` | Administrateur |
| `karim.benali@fabrique.example` | Technicien |
| `camille.durand@fabrique.example` | Adhérent |
| `lucas.moreau@fabrique.example` | Adhérent |
| `ines.petit@fabrique.example` | Adhérent |
| `hugo.roux@fabrique.example` | Adhérent |

## Pages et API

### Pages Web
| Route | Accès | Description |
| --- | --- | --- |
| `GET /` | Public | Catalogue public des équipements avec recherche et filtres |
| `GET /equipements/{id}` | Public | Fiche détaillée d'un équipement et signalement d'incident |
| `GET /login`, `POST /login`, `POST /logout` | Public / Connecté | Authentification et déconnexion |
| `GET /mon-espace` | Connecté | Espace personnel (réservations, incidents signalés, filtres) |
| `GET /technicien` | Technicien / Admin | Tableau de bord technicien (demandes à confirmer, incidents, suivi) |
| `GET /incidents/{id}` | Connecté (auteur ou Tech/Admin) | Détail d'un incident, historique des états et interventions |

### Endpoints API JSON & Actions métier
| Méthode & Route | Accès | Description |
| --- | --- | --- |
| `GET /api/equipment`, `GET /api/equipment/{id}` | Public | Consultation du catalogue et des fiches au format JSON |
| `POST /api/reservations` | Connecté | Création d'une réservation (contrôle R1, R3 et chevauchements) |
| `POST /reservations/{id}/annuler` | Connecté (propriétaire ou Tech/Admin) | Annulation d'une réservation |
| `POST /reservations/{id}/confirmer` | Technicien / Admin | Confirmation d'une demande en attente (`DEMANDEE → CONFIRMEE`) |
| `POST /incidents` | Connecté | Signalement d'un nouvel incident |
| `POST /incidents/{id}/transition` | Technicien / Admin | Changement d'état (avancement ou réouverture avec motif) |
| `POST /incidents/{id}/bloquant` | Technicien / Admin | Qualification ou levée du caractère bloquant (R3) |

## Tests automatisés

La commande `composer test:db` exécute la batterie de tests d'intégration sur la base de données (avec nettoyage automatique restaurant l'état initial du jeu d'essai) :
* **Catalogue** (`test_catalogue_filtres.php`) : recherche par mot-clé, localisation, catégorie, statut et visibilité publique.
* **Réservations & R1** (`test_reservation.php`) : créneaux ordinaires/prioritaires (`ATELIER_PRIORITAIRE`), chevauchements (`409`), dates invalides (`422`).
* **Recette globale** (`test_recette_mardi.php`) : parcours complet adhérent et technicien.
* **Cycle de vie incidents & R6** (`test_r6_incidents.php`) : machine à états `INC-MAT-01`, triggers d'historique, motif obligatoire à la réouverture, blocage R3.
* **Confirmation technicien** (`test_confirmation_demande.php`) : contrôle des rôles, conflits de créneaux et statuts invalides.
* **Évolution EV-MAT-01** (`test_ev_mat_01.php`) : validation des règles et statuts HTTP de l'évolution.

## Sécurité

* **Protection SQL :** Requêtes préparées via Medoo et PDO (nettoyage systématique des variables de session SQL `@app_user_id` et `@motif_changement` après transaction).
* **Protection XSS :** Échappement systématique des données affichées dans les gabarits HTML.
* **Protection CSRF :** Validation des jetons CSRF sur les formulaires et actions d'écriture (non contournable via l'en-tête `Accept: application/json`).
* **Contrôle d'accès (RBAC) :** Vérification stricte des rôles (`ADHERENT`, `TECHNICIEN`, `ADMIN`) et de la propriété des ressources.

## Avancement du projet

| Module / Fonctionnalité | État |
| --- | --- |
| Conception (Cahier des charges, MCD/MLD, dictionnaire de données, maquettes) | Terminé |
| Base de données (Schéma, jeu d'essai, procédures, triggers, vues SQL `01` à `05`) | Terminé |
| Authentification, gestion des rôles et sécurité (CSRF, XSS, RBAC) | Terminé |
| Catalogue public, recherche, filtres et API JSON | Terminé |
| Réservations, créneaux prioritaires (R1), annulation et confirmation technicien | Terminé |
| Évolution `EV-MAT-01` (code et tests automatisés) | Terminé (documentation à finaliser) |
| Cycle de vie des incidents (`R6` / `INC-MAT-01`) et blocage des réservations (`R3`) | Terminé |
| Formulaire HTML de signalement d'incident & marquage `a_verifier` (R3) | En cours |
| Gestion des interventions techniques (`INTERVENTION` / `PARTICIPER`) | En cours |
| Extensions (`R8`, `R9`, `R10`) et finalisation recette / démonstration | À faire |

## Auteur

Projet BTS RepairLab - Debaisieux Matthieu