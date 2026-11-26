<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\ReservationRefusee;
use App\Repository\CreneauPrioritaireRepository;
use App\Repository\ReservationRepository;
use InvalidArgumentException;


    /**
     * Point unique de décision pour une réservation : e formulaire HTML et l'API l'appellent
     * tous les deux, et seul ce service (avec la procédure stockée qu'il appelle) produit un refus.
     *
     * Ordre des contrôles, donc priorité des motifs quand plusieurs s'appliquent :
     *   1. droits         : ATELIER_PRIORITAIRE (403)
     *   2. validation     : DATES_INVALIDES, DATE_PASSEE, EQUIPEMENT_INCONNU (procédure)
     *   3. état machine   : PANNE_BLOQUANTE (409, procédure)
     *   4. disponibilité  : CRENEAU_OCCUPE (409, procédure)
     */

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

        $estStaff = in_array($roleUtilisateur, ['TECHNICIEN', 'ADMIN'], true);
        $creneau = $this->creneauRepository->findChevauchant($idEquipement, $dateDebut, $dateFin);

        // R1 : un créneau réservé à un atelier encadré est inaccessible à un adhérent ;
        // un technicien ou un administrateur affecte la machine à l'atelier.
        if ($creneau !== null && !$estStaff) {
            throw ReservationRefusee::motif('ATELIER_PRIORITAIRE');
        }

        // La procédure stockée refuse (dates, équipement, PANNE_BLOQUANTE, CRENEAU_OCCUPE)
        // puis insère dans la même transaction verrouillée.
        return $this->reservationRepository->create(
            userId: $idUtilisateur,
            equipmentId: $idEquipement,
            start: $dateDebut,
            end: $dateFin,
            statutInitial: 'CONFIRMEE',
            idCreneau: $creneau !== null ? (int) $creneau['id_creneau'] : null
        );
    }

    /** Créneaux à venir qui bloquent une machine (sans donnée personnelle). */
    public function creneauxPris(int $idEquipement): array
    {
        return $this->reservationRepository->takenSlots($idEquipement);
    }

    /** @return array<int, array<string, mixed>> */
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
    public function annuler(int $idReservation, int $idUtilisateur, string $roleUtilisateur): array {
        $reservation = $this->reservationRepository->findById($idReservation);

        if ($reservation === null) {
            throw ReservationRefusee::motif('RESERVATION_INTROUVABLE');
        }

        $estProprietaire = (int) $reservation['id_utilisateur'] === $idUtilisateur;
        $estStaff = in_array($roleUtilisateur, ['TECHNICIEN', 'ADMIN'], true);

        if (!$estProprietaire && !$estStaff) {
            throw ReservationRefusee::motif('ACCES_REFUSE', 403, 'Vous ne pouvez pas annuler cette réservation.');
        }

        if (in_array((string) $reservation['statut'], ['ANNULEE', 'TERMINEE'], true)) {
            throw ReservationRefusee::motif('TRANSITION_INVALIDE', 422, 'Cette réservation ne peut plus être annulée (statut : ' . $reservation['statut'] . ').');
        }

        $this->reservationRepository->updateStatut($idReservation, 'ANNULEE');

        return $this->reservationRepository->find($idReservation) ?? ['id' => $idReservation];
    }

    /**
     * Confirmation d'une demande (statut DEMANDEE) par un technicien ou un administrateur.
     * Depuis EV-MAT-01 plus aucun parcours ne crée de DEMANDEE : la fonction reste disponible
     * pour un usage futur (voir documentation/evolution-EV-MAT-01.md).
     *
     * @return array<string, mixed>
     * @throws ReservationRefusee
     */
    public function confirmerDemande(
        int $idReservation,
        string $roleUtilisateur
    ): array {
        if (!in_array($roleUtilisateur, ['TECHNICIEN', 'ADMIN'], true)) {
            throw ReservationRefusee::motif('ACCES_REFUSE', 403, 'Seul un technicien ou un administrateur peut confirmer une demande.');
        }

        $reservation = $this->reservationRepository->findById($idReservation);

        if ($reservation === null) {
            throw ReservationRefusee::motif('RESERVATION_INTROUVABLE');
        }

        if ((string) $reservation['statut'] !== 'DEMANDEE') {
            throw ReservationRefusee::motif('STATUT_INVALIDE', 422, 'Seule une réservation au statut DEMANDEE peut être confirmée.');
        }

        $conflit = $this->reservationRepository->hasConfirmedConflict(
            idEquipement: (int) $reservation['id_equipement'],
            dateDebut: (string) $reservation['date_debut'],
            dateFin: (string) $reservation['date_fin'],
            excludeReservationId: $idReservation
        );

        if ($conflit) {
            throw ReservationRefusee::motif('CRENEAU_OCCUPE', 409, 'Un créneau confirmé chevauche déjà cette demande.');
        }

        $this->reservationRepository->updateStatut($idReservation, 'CONFIRMEE');

        return $this->reservationRepository->find($idReservation) ?? ['id' => $idReservation];
    }
}