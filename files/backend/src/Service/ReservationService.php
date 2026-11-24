<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\CreneauPrioritaireRepository;
use App\Repository\ReservationRepository;
use InvalidArgumentException;

final class ReservationService
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly CreneauPrioritaireRepository $creneauRepository
    ) {}

    /**
     * @return array{resultat: string, id_reservation: ?int}
     */
    public function creer(
        int $idEquipement,
        int $idUtilisateur,
        string $roleUtilisateur,
        string $dateDebut,
        string $dateFin
    ): array {
        if (!in_array($roleUtilisateur, ['ADHERENT', 'TECHNICIEN', 'ADMIN'], true)) {
            throw new InvalidArgumentException('Rôle utilisateur invalide.');
        }

        $creneau = $this->creneauRepository->findChevauchant($idEquipement, $dateDebut, $dateFin);

        if ($creneau === null) {
            // Créneau ordinaire : confirmation directe (sous réserve des conflits / R3 dans la SP)
            $idCreneau = null;
            $statutInitial = 'CONFIRMEE';
        } else {
            // Créneau prioritaire détecté
            $idCreneau = (int) $creneau['id_creneau'];

            $estTechnicienAutorise = in_array($roleUtilisateur, ['TECHNICIEN'], true);
            $statutInitial = $estTechnicienAutorise ? 'CONFIRMEE' : 'DEMANDEE';
        }

        return $this->reservationRepository->creerReservation(
            idEquipement: $idEquipement,
            idUtilisateur: $idUtilisateur,
            dateDebut: $dateDebut,
            dateFin: $dateFin,
            statutInitial: $statutInitial,
            idCreneau: $idCreneau
        );
    }
}