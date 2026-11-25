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
        $estStaff = in_array($roleUtilisateur, ['TECHNICIEN', 'ADMIN'], true);

        //Créneau réservé à un atelier encadré inaccessible aux adhérents
        if ($creneau !== null && !$estStaff) {
            throw ReservationRefusee::motif('ATELIER_PRIORITAIRE', 403);
        }

        $idCreneau = $creneau !== null ? (int) $creneau['id_creneau'] : null;
        $statutInitial = $estStaff ? 'CONFIRMEE' : 'DEMANDEE';

        //sp_creer_reservation lève ReservationRefusee (ex. CRENEAU_OCCUPE / 409) en cas de conflit
        return $this->reservationRepository->create(
            userId: $idUtilisateur,
            equipmentId: $idEquipement,
            start: $dateDebut,
            end: $dateFin,
            statutInitial: $statutInitial,
            idCreneau: $idCreneau
        );
    }
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listerParUtilisateur(int $idUtilisateur): array
    {
        if ($idUtilisateur <= 0) {
            throw new InvalidArgumentException('Identifiant utilisateur invalide.');
        }

        return $this->reservationRepository->findByUser($idUtilisateur);
    }

    /**
     * @return array<string, mixed>
     * @throws ReservationRefusee
     */
    public function annuler(
        int $idReservation,
        int $idUtilisateur,
        string $roleUtilisateur
    ): array {
        $reservation = $this->reservationRepository->findById($idReservation);

        if ($reservation === null) {
            throw new ReservationRefusee('Réservation introuvable.', 'RESERVATION_INTROUVABLE', 404);
        }

        $estProprietaire = (int) $reservation['id_utilisateur'] === $idUtilisateur;
        $estTechnicien   = in_array($roleUtilisateur, ['TECHNICIEN', 'ADMIN'], true);

        if (!$estProprietaire && !$estTechnicien) {
            throw new ReservationRefusee(
                'Vous ne pouvez pas annuler cette réservation.',
                'ACCES_REFUSE',
                403
            );
        }

        if (in_array((string) $reservation['statut'], ['ANNULEE', 'TERMINEE'], true)) {
            throw new ReservationRefusee(
                'Cette réservation ne peut plus être annulée (statut : ' . $reservation['statut'] . ').',
                'TRANSITION_INVALIDE',
                422
            );
        }

        $this->reservationRepository->updateStatut($idReservation, 'ANNULEE');

        return $this->reservationRepository->findById($idReservation) ?? $reservation;
    }

    /**
     * R1 : Confirmation d'une demande sur créneau prioritaire par un technicien.
     *
     * @return array<string, mixed>
     * @throws ReservationRefusee
     */
    public function confirmerDemande(
        int $idReservation,
        string $roleUtilisateur
    ): array {
        if (!in_array($roleUtilisateur, ['TECHNICIEN', 'ADMIN'], true)) {
            throw new ReservationRefusee(
                'Seul un technicien ou administrateur peut confirmer une demande.',
                'ACCES_REFUSE',
                403
            );
        }

        $reservation = $this->reservationRepository->findById($idReservation);

        if ($reservation === null) {
            throw new ReservationRefusee('Réservation introuvable.', 'RESERVATION_INTROUVABLE', 404);
        }

        if ((string) $reservation['statut'] !== 'DEMANDEE') {
            throw new ReservationRefusee(
                'Seule une réservation au statut DEMANDEE peut être confirmée.',
                'STATUT_INVALIDE',
                422
            );
        }

        $conflit = $this->reservationRepository->hasConfirmedConflict(
            idEquipement: (int) $reservation['id_equipement'],
            dateDebut: (string) $reservation['date_debut'],
            dateFin: (string) $reservation['date_fin'],
            excludeReservationId: $idReservation
        );

        if ($conflit) {
            throw new ReservationRefusee(
                'Un créneau confirmé chevauche déjà cette demande.',
                'CONFLIT_CRENEAU',
                409
            );
        }

        $this->reservationRepository->updateStatut($idReservation, 'CONFIRMEE');

        return $this->reservationRepository->findById($idReservation) ?? $reservation;
    }
}