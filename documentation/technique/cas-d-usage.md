# Acteurs et cas d'usage — RepairLab

## Acteurs

| Acteur | Rôle |
|---|---|
| Visiteur | Consulte la vitrine et le catalogue public |
| Adhérent | Réserve des créneaux, signale un problème |
| Technicien | Traite les incidents et les interventions, valide les demandes prioritaires |
| Administrateur | Gère les comptes et l'inventaire |

## Cas d'usage

| N° | Cas d'usage | Acteur | Règle |
|---|---|---|---|
| UC1 | Consulter le catalogue et la fiche d'un équipement | Visiteur, adhérent | Socle |
| UC2 | Se connecter, se déconnecter | Tous | Socle |
| UC3 | Voir la disponibilité d'une machine avec sa raison | Visiteur, adhérent | R9 |
| UC4 | Réserver un créneau | Adhérent | Socle, R1, R3 |
| UC5 | Annuler une réservation, consulter « mes créneaux » | Adhérent | Socle |
| UC6 | Confirmer une demande sur créneau prioritaire | Technicien autorisé | R1 |
| UC7 | Signaler un problème sur une machine | Adhérent | Socle |
| UC8 | Faire avancer un incident (démarrer, résoudre, clôturer, rouvrir) | Technicien | R6 |
| UC9 | Renseigner le dossier d'intervention et remettre la machine en service | Technicien | R10 |
| UC10 | Documenter un contrôle et lever `CONTROLE_REQUIS` | Technicien | R8 |
| UC11 | Consulter le tableau de bord de disponibilité | Technicien | Socle, R8, R9 |
| UC12 | Gérer les comptes et l'inventaire | Administrateur | Socle |

## Scénarios de démonstration

**R1 — Réservation prioritaire.** Je suis un adhérent, je veux réserver l'imprimante 3D pendant l'atelier d'initiation. Comme ce créneau est réservé aux ateliers encadrés, l'application ne le confirme pas et la demande reste `DEMANDEE`. Un technicien autorisé peut ensuite la confirmer.

**R3 — Panne bloquante.** Je suis un adhérent, je veux réserver l'imprimante Bambu. Comme un incident bloquant est ouvert (`OUVERT` ou `EN_COURS`), l'application refuse la réservation. Ma réservation déjà existante est conservée et marquée « à vérifier ».

**R6 — Procédure d'un incident.** Je suis un adhérent, je veux clôturer un incident. Comme seul un technicien peut changer l'état et que l'ordre est `OUVERT`, `EN_COURS`, `RESOLU`, `CLOTURE`, l'application refuse avec un code 403. Un technicien passe ensuite l'incident à l'état suivant, et le changement est tracé.

**R8 — Trois pannes en trente jours.** Je suis un technicien, je signale un troisième incident technique sur la même machine en trente jours. Comme le seuil est atteint, le tableau de bord affiche `CONTROLE_REQUIS` jusqu'à un contrôle documenté.

**R9 — Disponibilité expliquée.** Je suis un adhérent, je veux savoir si la découpeuse est libre jeudi. Comme elle est en maintenance ce jour-là, l'application répond `MAINTENANCE` et donne la raison.

**R10 — Validation qualité.** Je suis un technicien, je veux remettre l'imprimante en service. Comme le dossier n'a ni action ni résultat de test, l'application refuse. Après saisie du dossier complet, la remise en service est acceptée.
