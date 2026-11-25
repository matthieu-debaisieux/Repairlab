# RepairLab — La Fabrique Commune

Application web de gestion d'un FabLab associatif : équipements, réservations de créneaux, incidents et interventions.
Projet réalisé pendant la semaine intensive BTS SIO SLAM, du 23 au 27 novembre 2026.

> Promesse : une machine en panne ou déjà réservée ne peut pas être utilisée par erreur.

## Règles métier retenues

| Règle | Type | Principe |
|---|---|---|
| R1 — Réservation prioritaire | Contractuelle | Certains créneaux sont réservés aux ateliers encadrés : un adhérent obtient une demande (`DEMANDEE`), un technicien confirme. |
| R3 — Panne bloquante | Contractuelle | Un incident bloquant interdit les nouvelles réservations. |
| R6 — Procédure d'un incident | Contractuelle | `OUVERT → EN_COURS → RESOLU → CLOTURE`, retour arrière tracé. |
| R8 — Trois pannes en trente jours | Extension | Indicateur `CONTROLE_REQUIS`. |
| R9 — Disponibilité expliquée | Extension | Statut précis avec la raison. |
| R10 — Validation qualité | Extension | Dossier complet exigé avant la remise en service. |

Le détail est dans [`documentation/cahier-des-charges.md`](documentation/cahier-des-charges.md).

## Technologies

- PHP 8.2+, Slim 4, Medoo (au-dessus de PDO), Composer
- MySQL 5.7+ ou MariaDB 10.2+ (les contraintes `CHECK` ne sont appliquées qu'à partir de MySQL 8.0.16 et MariaDB 10.2.1 ; avec une version plus ancienne, les contrôles sont faits dans les services PHP)
- Architecture : Route → Contrôleur → Service → Repository → SQL
- Front React / Tailwind CSS / DaisyUI : prévu, à valider avec l'enseignant

## Structure du dépôt

```text
documentation/        cahier des charges, MCD (Looping), maquettes, documents techniques
  technique/          architecture MVC, contrat API, cas d'usage
files/backend/        application PHP
  database/           01_schema.sql, 02_seed.sql, 03_procedures.sql (à exécuter dans cet ordre)
  src/                contrôleurs, services, repositories, middlewares
  routes/ templates/  routes Slim et vues HTML
  tests/              tests PHP
```

## Installation

Prérequis : PHP 8.2+ avec `pdo_mysql`, Composer, un serveur MySQL ou MariaDB.

1. Installer les dépendances et préparer la configuration :
   ```bash
   cd files/backend
   composer install
   cp .env.example .env     # puis renseigner DB_USER et DB_PASSWORD (le fichier .env n'est jamais commité)
   ```
2. Créer la base (nom par défaut : `bts_web`, jeu de caractères `utf8mb4`) puis exécuter les scripts **dans l'ordre**, avec phpMyAdmin (onglet Importer) ou en ligne de commande :
   ```bash
   mysql -u <utilisateur> -p bts_web < database/01_schema.sql
   mysql -u <utilisateur> -p bts_web < database/02_seed.sql
   mysql -u <utilisateur> -p bts_web < database/03_procedures.sql
   ```
   Les scripts se rejouent sur une base existante : `01_schema.sql` supprime puis recrée les tables.
3. Lancer le serveur de développement :
   ```bash
   composer serve
   ```
   Puis ouvrir <http://127.0.0.1:8080/>.

## Comptes de démonstration

Mot de passe de tous les comptes : `repairlab2026`. Ces comptes et ce mot de passe sont publics : à supprimer ou changer avant tout déploiement réel.

| E-mail | Rôle |
|---|---|
| alice.martin@fabrique.example | Administrateur |
| karim.benali@fabrique.example | Technicien |
| camille.durand@fabrique.example | Adhérent |
| lucas.moreau@fabrique.example | Adhérent |
| ines.petit@fabrique.example | Adhérent |
| hugo.roux@fabrique.example | Adhérent |

## Pages et API

| Route | Accès |
|---|---|
| `GET /` | Catalogue public avec recherche et filtres |
| `GET /login`, `POST /login`, `POST /logout` | Connexion et déconnexion |
| `GET /mon-espace` | Toute personne connectée |
| `GET /technicien` | Technicien et administrateur |
| `GET /api/equipment`, `GET /api/equipment/{id}` | Public |
| `POST /api/reservations` | Connecté, `Content-Type: application/json` |

Le contrat complet est dans [`documentation/technique/contrat-api.md`](documentation/technique/contrat-api.md).

## Tests

```bash
cd files/backend
composer test       # contrat JSON, sans base de données
composer test:db    # catalogue et réservations ; utilise la base du .env, y crée puis supprime ses propres lignes
```

## Sécurité

- Mots de passe hachés (`password_hash`), session régénérée à la connexion, cookie `HttpOnly` et `SameSite=Lax`.
- Jeton CSRF sur les formulaires ; sur l'API, les écritures exigent du JSON et une origine identique.
- Droits vérifiés côté serveur par un middleware ; requêtes SQL paramétrées ; sorties HTML échappées.
- `APP_DEBUG=false` pour toute démonstration ou production.

## Avancement

| Étape | État |
|---|---|
| Cahier des charges, MCD, schéma SQL, maquettes | Fait |
| Authentification et rôles | Fait |
| Catalogue, recherche et filtres | Fait |
| Réservation avec contrôle du chevauchement (procédure stockée) | Fait |
| R1 : réservation prioritaire (demande, puis confirmation par un technicien) | Demande faite, confirmation à faire |
| Annulation, « mes créneaux », incidents, interventions | À faire |
| R3, R6, vues SQL, trigger | À faire |
| Extensions R8, R9, R10 | À faire |
| Recette, documentation de déploiement | À faire |

## Auteur

[Ton nom] — BTS SIO SLAM.
