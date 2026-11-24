<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\ReservationRefusee;
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
     * @return array<string, mixed>
     * @throws ReservationRefusee
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
            $idCreneau = null;
            $statutInitial = 'CONFIRMEE';
        } else {
            $idCreneau = (int) $creneau['id_creneau'];
            $estTechnicienAutorise = in_array($roleUtilisateur, ['TECHNICIEN', 'ADMIN'], true);
            $statutInitial = $estTechnicienAutorise ? 'CONFIRMEE' : 'DEMANDEE';
        }

        return $this->reservationRepository->create(
            userId: $idUtilisateur,
            equipmentId: $idEquipement,
            start: $dateDebut,
            end: $dateFin,
            statutInitial: $statutInitial,
            idCreneau: $idCreneau
        );
    }
}