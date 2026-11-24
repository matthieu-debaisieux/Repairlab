<?php

declare(strict_types=1);

namespace App\Service;

use Medoo\Medoo;

final class TechnicienDashboardService
{
    public function __construct(
        private readonly Medoo $database
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getDashboardData(): array
    {
        // 1. Réservations en attente de validation (statut DEMANDEE - R1)
        $pendingReservations = $this->database->select(
            'RESERVATION (R)',
            [
                '[>]EQUIPEMENT (E)' => ['id_equipement' => 'id_equipement'],
                '[>]UTILISATEUR (U)' => ['id_utilisateur' => 'id_utilisateur'],
            ],
            [
                'R.id_reservation',
                'R.date_debut',
                'R.date_fin',
                'R.statut',
                'E.id_equipement',
                'E.nom (equipement_nom)',
                'U.nom (utilisateur_nom)',
            ],
            [
                'R.statut' => 'DEMANDEE',
                'ORDER' => ['R.date_debut' => 'ASC'],
            ]
        );

        // 2. Incidents actifs (non clôturés)
        $openIncidents = $this->database->select(
            'INCIDENT (I)',
            [
                '[>]EQUIPEMENT (E)' => ['id_equipement' => 'id_equipement'],
            ],
            [
                'I.id_incident',
                'I.titre',
                'I.statut',
                'I.est_bloquant',
                'I.description',
                'I.date_signalement',
                'E.id_equipement',
                'E.nom (equipement_nom)',
            ],
            [
                'I.statut[!]' => 'CLOTURE',
                'ORDER' => ['I.id_incident' => 'DESC'],
            ]
        );

        // 3. Équipements hors service
        $outOfServiceEquipments = $this->database->select(
            'EQUIPEMENT',
            ['id_equipement', 'nom', 'statut_technique', 'localisation'],
            [
                'statut_technique' => 'HORS_SERVICE',
                'ORDER' => ['id_equipement' => 'DESC'],
            ]
        );

        $pendingList = is_array($pendingReservations) ? $pendingReservations : [];
        $incidentsList = is_array($openIncidents) ? $openIncidents : [];
        $outList = is_array($outOfServiceEquipments) ? $outOfServiceEquipments : [];

        return [
            'kpis' => [
                'pending_reservations_count' => count($pendingList),
                'open_incidents_count' => count($incidentsList),
                'out_of_service_count' => count($outList),
            ],
            'pending_reservations' => $pendingList,
            'open_incidents' => $incidentsList,
            'out_of_service_equipments' => $outList,
        ];
    }
}