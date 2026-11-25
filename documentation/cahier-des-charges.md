# Cahier des charges — RepairLab (La Fabrique Commune)

Projet BTS SIO SLAM, semaine intensive du 23 au 27 novembre 2026.
Dernière mise à jour : 24 novembre 2026.

Règles retenues : **contractuelles** R1, R3, R6 ; **extensions** R8, R9, R10.

## 1. Contexte et objectif

Le FabLab associatif « La Fabrique Commune » possède des imprimantes 3D, une découpeuse, des postes de soudure, des PC et des outils partagés. La direction veut un outil simple, pas un ERP, pour référencer les machines, réserver des créneaux, organiser la maintenance et rendre la disponibilité fiable.

**Promesse du produit :** « Une machine en panne ou déjà réservée ne peut pas être utilisée par erreur. »

## 2. Acteurs et droits

| Acteur | Ce qu'il fait |
|---|---|
| Visiteur (non connecté) | Consulte la vitrine et le catalogue public, sans donnée personnelle. |
| Adhérent | Réserve et annule ses créneaux, consulte « mes créneaux », signale un problème. |
| Technicien | Suit et traite les incidents et les interventions, remet les machines en service. Un technicien autorisé valide les demandes sur créneaux prioritaires. |
| Administrateur | Gère les comptes et l'inventaire. |

Les droits sont vérifiés **côté serveur**, pour les formulaires comme pour l'API. Chaque utilisateur a un seul rôle.

## 3. Périmètre

**Inclus :** vitrine publique, authentification par rôle, inventaire des équipements, réservations, incidents et interventions, tableau de bord de disponibilité, API JSON, traçabilité.

**Exclus :** paiement, contrôle électronique des machines, badges, planning RH, IoT, vente de pièces.

Aucune table de qualification, de stock de pièces ou de pointage n'est créée : aucune règle retenue ne le justifie.

## 4. Besoins fonctionnels du socle

1. Présenter le lieu et le catalogue public des équipements.
2. Connecter un utilisateur (compte, session, déconnexion) avec trois rôles.
3. Gérer l'inventaire : catégorie, statut technique, localisation.
4. Réserver un équipement sur une période (début, fin) avec les états `DEMANDEE`, `CONFIRMEE`, `ANNULEE`, `TERMINEE`.
5. Signaler un incident, puis le traiter par une ou plusieurs interventions (diagnostic, action, état).
6. Rechercher et filtrer, avec un état « aucun résultat ».
7. Afficher un tableau de bord de disponibilité.
8. Exposer au moins une ressource en lecture et une écriture métier via l'API.
9. Tracer l'auteur et la date d'un incident, et les dates d'une intervention.

### Invariants du socle (toujours vrais)

- Le début précède la fin.
- L'équipement réservé existe.
- Deux réservations incompatibles sur le même équipement et le même créneau sont refusées.
- Aucune intervention ne peut porter sur un incident inexistant.

## 5. Règles contractuelles

### R1 — La réservation prioritaire

**Énoncé.** Certains créneaux sont réservés en priorité aux ateliers encadrés. Un adhérent ordinaire ne peut pas confirmer une réservation sur ces créneaux ; un responsable ou technicien autorisé peut affecter la machine à l'atelier.

**Décisions retenues.**
- La priorité est une propriété d'une **plage horaire** d'un équipement, pas de la machine ni de la réservation.
- On mémorise l'atelier concerné : son nom, sa date de début et sa date de fin (entité `CRENEAU_PRIORITAIRE`).
- Une demande d'adhérent sur un créneau prioritaire passe à l'état `DEMANDEE`. Elle est ensuite validée par un technicien autorisé.
- Une réservation sur un créneau ordinaire se confirme normalement.

**Démo.** Créneau réservé à l'atelier impression ; deux profils, deux résultats.
**Tests.** Adhérent : pas de confirmation ; technicien autorisé : confirmation ; créneau ordinaire : confirmation.

### R3 — Panne bloquante

**Énoncé.** Un incident bloquant ouvert interdit toute nouvelle réservation de la machine. Les réservations existantes sont conservées et signalées « à vérifier » ; elles ne sont pas supprimées silencieusement.

**Décisions retenues.**
- Un incident est bloquant parce qu'un **technicien le décide** (attribut `est_bloquant`).
- Un incident bloquant est « ouvert » tant qu'il est à l'état `OUVERT` ou `EN_COURS` : il interdit les nouvelles réservations jusqu'à `RESOLU`.
- Le marquage « à vérifier » est **mémorisé** (colonne `a_verifier` de `RESERVATION`) : il est posé sur les réservations futures de la machine quand un incident bloquant s'ouvre.
- Au passage à `RESOLU`, les nouvelles réservations redeviennent possibles. Le marquage « à vérifier » n'est pas effacé automatiquement : il est conservé comme trace, et un technicien traite chaque réservation concernée (maintien ou annulation).

**Démo.** Machine disponible, puis incident bloquant.
**Tests.** Nouvelle réservation refusée ; historique conservé ; retour à la normale après résolution.

### R6 — Un incident suit une procédure

**Énoncé.** États autorisés : `OUVERT → EN_COURS → RESOLU → CLOTURE`. Seul un technicien peut commencer, résoudre ou clôturer. Un retour arrière exige une réouverture explicite et tracée.

**Décisions retenues.**
- L'état courant est une colonne `statut` de l'incident, et chaque changement est **mémorisé dans `HISTORIQUE_INCIDENT`** (ancien état, nouvel état, date, auteur, motif), alimenté par un trigger quand `OLD.statut <> NEW.statut`.
- Une transition interdite renvoie une erreur HTTP 403 avec un message : « Vous ne pouvez pas revenir en arrière, rouvrez un ticket. »
- Seul un technicien peut changer l'état.

**Démo.** Tentative de clôture directe par un adhérent, puis transition correcte.
**Tests.** 403 ; transition interdite ; trace présente.

## 6. Extensions

### R8 — Trois pannes en trente jours

**Énoncé.** Si une machine cumule trois incidents techniques distincts ouverts durant les trente derniers jours, son tableau de bord affiche `CONTROLE_REQUIS`. Un technicien peut lever cet indicateur après un contrôle documenté.

**Décisions retenues.**
- Date de référence : la date de signalement de l'incident (`date_signalement`).
- Un **incident technique** est un problème physique (câble, écran) ou logiciel (application, système). Un manque de consommable (par exemple l'encre) n'en est pas un. Attribut `est_technique`.
- **Distinct** : trois incidents différents à des moments différents, pas un incident aux problèmes multiples.
- L'indicateur est **mémorisé** (colonne `controle_requis` de `EQUIPEMENT`).
- Le contrôle documenté est enregistré dans `CONTROLE` : équipement, technicien, date, compte rendu.
- Pour éviter que l'indicateur revienne aussitôt, seuls les incidents postérieurs au dernier contrôle comptent.

**Démo.** Deuxième puis troisième incident.
**Tests.** Seuil ; incident ancien ignoré ; levée tracée.

### R9 — La vraie disponibilité expliquée

**Énoncé.** Pour une période demandée, l'application annonce un statut parmi `DISPONIBLE`, `DEJA_RESERVE`, `HORS_SERVICE`, `MAINTENANCE`, avec la raison.

**Décisions retenues.**
- `HORS_SERVICE` : machine non disponible pour personne. `MAINTENANCE` : un technicien travaille dessus, sur une période datée (`MAINTENANCE.date_debut` et `date_fin`).
- Si plusieurs causes coexistent, **`MAINTENANCE` l'emporte** sur `HORS_SERVICE`.
- Le calcul s'appuie uniquement sur les états et horaires du socle, indépendamment des autres règles : il ne suppose ni incident bloquant ni créneau prioritaire.

**Démo.** Changer la date et l'état : raisons différentes.
**Tests.** Réservation concurrente ; maintenance ; libre.

### R10 — Validation qualité avant remise en service

**Énoncé.** Après une intervention, une machine hors service ne peut redevenir disponible sans diagnostic, action effectuée et résultat de test enregistrés par un technicien.

**Décisions retenues.**
- Le dossier est rattaché à l'**intervention** (`diagnostic`, `action`, `resultat_test`).
- « Incomplet » signifie qu'un champ obligatoire est vide.
- Le technicien saisit le dossier, qui est mémorisé.

**Démo.** Remise en service refusée sans compte rendu, puis réussie avec dossier complet.
**Tests.** Dossier incomplet refusé ; dossier complet accepté.

## 7. Données

### Entités (MCD)

`UTILISATEUR`, `CATEGORIE_EQUIPEMENT`, `EQUIPEMENT`, `RESERVATION`, `CRENEAU_PRIORITAIRE`, `INCIDENT`, `HISTORIQUE_INCIDENT`, `INTERVENTION`, `CONTROLE`, `MAINTENANCE`, plus l'association N–N `PARTICIPER` entre techniciens et interventions.

### Valeurs autorisées

| Champ | Valeurs |
|---|---|
| `UTILISATEUR.role` | `ADMIN`, `TECHNICIEN`, `ADHERENT` |
| `EQUIPEMENT.statut_technique` | `EN_SERVICE`, `HORS_SERVICE` |
| `RESERVATION.statut` | `DEMANDEE`, `CONFIRMEE`, `ANNULEE`, `TERMINEE` |
| `INCIDENT.statut` | `OUVERT`, `EN_COURS`, `RESOLU`, `CLOTURE` |
| `INTERVENTION.etat` | `EN_COURS`, `TERMINEE` |

### Exigences

- MCD Merise, puis MLD avec clés primaires et étrangères, puis scripts SQL ordonnés dans `files/backend/database/`.
- Contraintes d'intégrité (clés, `CHECK`, `UNIQUE`), requêtes paramétrées, jeu d'essai cohérent.
- Aucune donnée personnelle réelle dans les jeux d'essai.
- Les informations calculables ne sont pas dupliquées sans justification écrite.

### Colonnes liées aux règles

- `RESERVATION.a_verifier` (R3) : marquage mémorisé des réservations à vérifier.
- `EQUIPEMENT.controle_requis` (R8) : indicateur mémorisé, levé après un contrôle.

## 8. SQL avancé (transversal)

Les objets suivants sont des propositions à valider. Ils sont versionnés dans Git et rejouables sur une base vide.

| Objet | Rôle prévu |
|---|---|
| Trigger | Historiser chaque changement d'état d'un incident dans `HISTORIQUE_INCIDENT` (R6). |
| Procédure stockée | À définir, appelée depuis un repository PHP. Piste : créer une réservation en vérifiant le chevauchement. |
| Vue 1 | Disponibilité d'un équipement (R9, tableau de bord). |
| Vue 2 | Incidents ouverts par équipement (R3, R8, tableau de bord). |

## 9. Contraintes techniques

- PHP 8.2+, Slim 4, MariaDB, PDO/Medoo, architecture Contrôleur → Service → Repository.
- Les règles s'appliquent de la même façon via le formulaire et via l'API.
- Front : React, Tailwind CSS et DaisyUI, à faire valider par l'enseignant. Aucune décision métier n'est prise côté navigateur.
- Mots de passe hachés, CSRF sur les formulaires authentifiés, requêtes paramétrées, aucun secret dans Git.
- API : statuts HTTP cohérents, jamais 200 systématique.

## 10. Maquettes

Huit écrans : vitrine d'accueil, connexion, catalogue public, fiche équipement avec réservation et messages de refus, mes créneaux, signalement d'incident, tableau de bord technicien, détail d'incident avec dossier de remise en service. Les captures sont dans `documentation/`.

## 11. Scénario de démonstration

1. Un adhérent réserve une imprimante depuis son compte.
2. Un technicien signale ou traite un problème.
3. La disponibilité est identique sur la fiche et dans l'API.
4. Trois scénarios de règles sont provoqués devant le jury.
5. Une évolution et une correction sont présentées.

## 12. Critères de recette

| Réf. | Scénario | Résultat attendu | Statut |
|---|---|---|---|
| R1-a | Adhérent sur créneau prioritaire | Pas de confirmation (`DEMANDEE`) | À faire |
| R1-b | Technicien sur créneau prioritaire | Confirmé | À faire |
| R1-c | Adhérent sur créneau ordinaire | Confirmé | À faire |
| R3-a | Réservation sur machine avec incident bloquant | Refusée | À faire |
| R3-b | Réservation existante lors d'un incident bloquant | Conservée et « à vérifier » | À faire |
| R3-c | Incident résolu | Retour à la normale | À faire |
| R6-a | Adhérent tente une clôture | 403 | À faire |
| R6-b | Transition `OUVERT` vers `CLOTURE` | Refusée | À faire |
| R6-c | Changement d'état valide | Trace dans l'historique | À faire |
| R8-a | Trois incidents techniques en 30 jours | `CONTROLE_REQUIS` | À faire |
| R8-b | Incident de plus de 30 jours | Ignoré | À faire |
| R9-a | Période réservée, en maintenance, libre | Statut et raison corrects | À faire |
| R10-a | Remise en service sans dossier complet | Refusée | À faire |
| R10-b | Remise en service avec dossier complet | Acceptée | À faire |

## 13. Hypothèses retenues

1. Le marquage « à vérifier » d'une réservation reste en base après la résolution de l'incident, comme trace historique (R3).
2. L'indicateur `CONTROLE_REQUIS` se pose à l'ouverture du troisième incident technique et se lève par un contrôle documenté (R8).

## 14. Journal des décisions

| Décision | Raison | Règle |
|---|---|---|
| Priorité portée par une plage horaire | Un créneau existe indépendamment des réservations | R1 |
| Bloquant décidé par un technicien | Une panne n'est pas bloquante par nature | R3 |
| Historique dans une table dédiée | Une colonne écraserait la trace des retours arrière | R6 |
| `est_technique` sur l'incident | Seuls les incidents techniques comptent | R8 |
| Maintenance prioritaire sur hors service | La maintenance est plus précise et datée | R9 |
| Dossier rattaché à l'intervention | C'est le travail du technicien | R10 |
| Validation par un technicien autorisé | C'est l'énoncé : responsable ou technicien autorisé | R1 |
| `EN_COURS` bloque les réservations | Une machine en réparation ne doit pas être réservée | R3 |
| « À vérifier » conservé en historique | On ne perd jamais la trace d'une réservation touchée | R3 |
| Date de signalement comme référence des 30 jours | Un incident ouvert n'a pas de date de fin | R8 |
| Un seul rôle par utilisateur | Simplifie les droits | Socle |
| `PARTICIPER` en N–N | Plusieurs techniciens peuvent intervenir | Socle |
