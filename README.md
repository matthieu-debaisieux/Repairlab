# RepairLab — La Fabrique Commune

Application web de gestion d'un FabLab associatif : équipements, réservations de créneaux, incidents et interventions.
Projet réalisé pendant la semaine intensive BTS SIO SLAM, du 23 au 27 novembre 2026.

> Promesse : une machine en panne ou déjà réservée ne peut pas être utilisée par erreur.

## Fonctionnalités

- Vitrine publique et catalogue des équipements.
- Connexion avec trois rôles : administrateur, technicien, adhérent (droits vérifiés côté serveur).
- Réservation de créneaux et suivi « mes créneaux ».
- Signalement d'incidents, interventions et dossier de remise en service.
- Tableau de bord de disponibilité, avec la raison du statut.
- API JSON partageant les règles métier du site.

## Règles métier retenues

| Règle | Type | Principe |
|---|---|---|
| R1 — Réservation prioritaire | Contractuelle | Certains créneaux sont réservés aux ateliers encadrés. |
| R3 — Panne bloquante | Contractuelle | Un incident bloquant interdit les nouvelles réservations. |
| R6 — Procédure d'un incident | Contractuelle | `OUVERT → EN_COURS → RESOLU → CLOTURE`, retour arrière tracé. |
| R8 — Trois pannes en trente jours | Extension | Indicateur `CONTROLE_REQUIS` sur le tableau de bord. |
| R9 — Disponibilité expliquée | Extension | Statut précis avec la raison. |
| R10 — Validation qualité | Extension | Dossier complet exigé avant la remise en service. |

Le détail est dans [`documentation/cahier-des-charges.md`](documentation/cahier-des-charges.md).

## Technologies

- PHP 8.2+, Slim 4, Medoo (au-dessus de PDO), Composer
- MariaDB
- Front : React, Tailwind CSS, DaisyUI
- Architecture : Contrôleur → Service → Repository → SQL

## Structure du dépôt

```text
documentation/   cahier des charges, MCD, MLD, maquettes
sql/             scripts SQL numérotés (schéma, jeu d'essai, vues, procédure, trigger)
starter-kit/     point de départ fourni (à faire évoluer)
```

Le code de l'application sera ajouté dans son propre dossier (à compléter).

## Installation

### Prérequis

- PHP 8.2 ou plus, Composer
- MariaDB 10.2 ou plus
- Node.js (pour le front)

### Base de données

Les scripts sont à rejouer dans l'ordre sur une base vide.

```bash
mysql -u <utilisateur> -p -e "CREATE DATABASE repairlab CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u <utilisateur> -p repairlab < sql/01_schema.sql
```

Les scripts suivants (jeu d'essai, vues, procédure stockée, trigger) seront ajoutés dans `sql/`.

### Application

```bash
composer install
cp .env.example .env     # puis renseigner l'accès à la base (le fichier .env n'est jamais commité)
```

Commande de lancement : à compléter.

## Comptes de démonstration

À compléter une fois le jeu d'essai écrit (un administrateur, un technicien, des adhérents). Aucune donnée personnelle réelle.

## Avancement

| Étape | État |
|---|---|
| Cahier des charges | Fait |
| MCD et schéma SQL | Fait |
| Maquettes | Fait |
| Jeu d'essai, vues, procédure, trigger | À faire |
| API et règles R1, R3, R6 | À faire |
| Extensions R8, R9, R10 | À faire |
| Tests, recette, documentation de déploiement | À faire |

## Documentation

- [Cahier des charges](documentation/cahier-des-charges.md)
- Schéma de la base : [`sql/01_schema.sql`](sql/01_schema.sql)

## Auteur

Matthieu Debaisieux — BTS SIO SLAM.
