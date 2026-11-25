# Contrat API initial — RepairLab

Version de travail du 24 novembre 2026. Les routes et les codes sont à ajuster pendant l'implémentation.

## Conventions

- Format JSON, encodage UTF-8, préfixe `/api`.
- Authentification : la même session que le site. Les écritures exigent `Content-Type: application/json` et, si l'en-tête `Origin` est présent, le même site que l'API (protection CSRF). Sans session, les routes protégées renvoient 401.
- Les règles métier sont identiques pour le site et pour l'API : elles vivent dans les services, jamais dans les vues ni dans le navigateur.
- Dates au format ISO 8601 (`2026-11-26T14:00:00`).
- Erreur : `{"error": "INCIDENT_BLOQUANT", "message": "Réservation impossible : un incident bloquant est ouvert."}`

## Codes de statut

| Code | Usage |
|---|---|
| 200 | Lecture ou modification réussie |
| 201 | Création réussie |
| 400 | Requête mal formée ou donnée invalide |
| 401 | Non connecté |
| 403 | Droit insuffisant ou transition d'état interdite (R6) |
| 404 | Ressource inconnue |
| 409 | Conflit de créneau (chevauchement de réservations) |
| 422 | Règle métier non respectée (incident bloquant, dossier incomplet) |

## Routes

| Méthode | Route | Qui | Entrée | Succès | Erreurs |
|---|---|---|---|---|---|
| GET | `/api/equipment` | Public | `?categorie=`, `?statut=` | 200 liste des équipements visibles | 400 |
| GET | `/api/equipment/{id}` | Public | | 200 fiche de l'équipement | 404 |
| GET | `/api/equipment/{id}/availability` | Public | `?debut=&fin=` | 200 `{statut, raison}` (R9) | 400, 404 |
| GET | `/api/reservations` | Connecté | `?equipmentId=` | 200 (un adhérent voit les siennes) | 401 |
| POST | `/api/reservations` | Adhérent | `equipmentId`, `debut`, `fin` | 201 réservation `CONFIRMEE`, ou `DEMANDEE` sur créneau prioritaire (R1) | 400, 401, 404, 409, 422 (R3) |
| PATCH | `/api/reservations/{id}` | Propriétaire | `statut: ANNULEE` | 200 | 401, 403, 404 |
| POST | `/api/reservations/{id}/confirmation` | Technicien autorisé | | 200 `CONFIRMEE` (R1) | 401, 403, 404, 409 |
| POST | `/api/incidents` | Connecté | `equipmentId`, `titre`, `description`, `nature` | 201 incident `OUVERT` | 400, 401, 404 |
| GET | `/api/incidents/{id}` | Connecté | | 200 incident et historique | 401, 404 |
| POST | `/api/incidents/{id}/transition` | Technicien | `nouvelEtat`, `motif` | 200 (R6) | 401, 403 (rôle ou transition interdite), 404 |
| POST | `/api/incidents/{id}/reouverture` | Technicien | `motif` obligatoire | 200 retour à `OUVERT`, tracé (R6) | 400, 401, 403, 404 |
| POST | `/api/interventions/{id}/remise-en-service` | Technicien | | 200 machine `EN_SERVICE` (R10) | 401, 403, 404, 422 dossier incomplet |
| POST | `/api/equipment/{id}/controles` | Technicien | `compte_rendu` | 201, lève `CONTROLE_REQUIS` (R8) | 400, 401, 403, 404 |

## Exemples de réponse

Disponibilité (R9) :

```json
{"statut": "MAINTENANCE", "raison": "Maintenance du 25/11 au 27/11 : remplacement du tube laser."}
```

Refus d'une transition (R6) :

```json
{"error": "TRANSITION_INTERDITE", "message": "Vous ne pouvez pas revenir en arrière : rouvrez un ticket."}
```

## Points à valider

- Un adhérent voit-il uniquement ses réservations ? (Proposé : oui.)
- La confirmation d'une demande sur créneau prioritaire passe par une route dédiée (R1).
