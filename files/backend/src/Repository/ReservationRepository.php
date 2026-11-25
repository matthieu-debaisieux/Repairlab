<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\ReservationRefusee;
use Medoo\Medoo;
use PDO;
use PDOException;

final class ReservationRepository
{
    public function __construct(private Medoo $database)
    {
    }
    /**
     * Crée la réservation via la procédure stockée (contrôle du chevauchement
     * et insertion dans une seule transaction).
     *
     * @throws ReservationRefusee
     */
    public function create(
        int $userId,
        int $equipmentId,
        string $start,
        string $end,
        string $statutInitial = 'CONFIRMEE',
        ?int $idCreneau = null
    ): array {
        $pdo = $this->database->pdo;

        try {
            $statement = $pdo->prepare(
                'CALL sp_creer_reservation(:u, :e, :d, :f, :s, :c, @new_id)'
            );
            $statement->bindValue(':u', $userId, PDO::PARAM_INT);
            $statement->bindValue(':e', $equipmentId, PDO::PARAM_INT);
            $statement->bindValue(':d', $start, PDO::PARAM_STR);
            $statement->bindValue(':f', $end, PDO::PARAM_STR);
            $statement->bindValue(':s', $statutInitial, PDO::PARAM_STR);
            $statement->bindValue(':c', $idCreneau, $idCreneau === null ? PDO::PARAM_NULL : PDO::PARAM_INT);

            $statement->execute();
            $statement->closeCursor();

            $id = (int) $pdo->query('SELECT @new_id')->fetchColumn();
        } catch (PDOException $e) {
            $refus = ReservationRefusee::fromPdo($e);
            if ($refus === null) {
                throw $e;
            }
            throw $refus;
        }

        return $this->find($id) ?? ['id' => $id];
    }

    public function find(int $id): ?array
    {
        $row = $this->database->get(
            'RESERVATION (R)',
            ['[>]EQUIPEMENT (E)' => ['id_equipement' => 'id_equipement']],
            [
                'R.id_reservation (id)',
                'R.date_debut (start)',
                'R.date_fin (end)',
                'R.statut (status)',
                'R.id_utilisateur (user_id)',
                'E.id_equipement (equipment_id)',
                'E.nom (equipment)',
            ],
            ['R.id_reservation' => $id]
        );

        return is_array($row) ? $row : null;
    }

    /** Créneaux à venir qui bloquent l'équipement, sans aucune donnée personnelle (affichage public). */
    public function takenSlots(int $equipmentId): array
    {
        return $this->database->select(
            'RESERVATION',
            [
                'date_debut (start)',
                'date_fin (end)',
            ],
            [
                'id_equipement' => $equipmentId,
                'statut' => ['DEMANDEE', 'CONFIRMEE'],
                'date_fin[>]' => date('Y-m-d H:i:s'),
                'ORDER' => ['date_debut' => 'ASC'],
            ]
        );
    }

    public function forUser(int $userId): array
    {
        return $this->database->select(
            'RESERVATION (R)',
            ['[>]EQUIPEMENT (E)' => ['id_equipement' => 'id_equipement']],
            [
                'R.id_reservation (id)',
                'R.date_debut (start)',
                'R.date_fin (end)',
                'R.statut (status)',
                'R.a_verifier (to_check)',
                'E.nom (equipment)',
            ],
            [
                'R.id_utilisateur' => $userId,
                'ORDER' => ['R.date_debut' => 'DESC'],
            ]
        );
    }

    public function cancel(int $id): void
    {
        $this->database->update(
            'RESERVATION',
            ['statut' => 'ANNULEE'],
            ['id_reservation' => $id]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $idReservation): ?array
    {
        $row = $this->database->get(
            'RESERVATION (R)',
            [
                '[>]EQUIPEMENT (E)' => ['id_equipement' => 'id_equipement'],
                '[>]UTILISATEUR (U)' => ['id_utilisateur' => 'id_utilisateur'],
                '[>]CRENEAU_PRIORITAIRE (C)' => ['id_creneau' => 'id_creneau'],
            ],
            [
                'R.id_reservation',
                'R.date_debut',
                'R.date_fin',
                'R.statut',
                'R.a_verifier',
                'R.id_utilisateur',
                'R.id_equipement',
                'R.id_creneau',
                'E.nom (equipement_nom)',
                'U.nom (utilisateur_nom)',
                'C.libelle_atelier',
            ],
            ['R.id_reservation' => $idReservation]
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByUser(int $idUtilisateur): array
    {
        $rows = $this->database->select(
            'RESERVATION (R)',
            [
                '[>]EQUIPEMENT (E)' => ['id_equipement' => 'id_equipement'],
                '[>]CRENEAU_PRIORITAIRE (C)' => ['id_creneau' => 'id_creneau'],
            ],
            [
                'R.id_reservation',
                'R.date_debut',
                'R.date_fin',
                'R.statut',
                'R.a_verifier',
                'R.id_equipement',
                'R.id_creneau',
                'E.nom (equipement_nom)',
                'E.localisation (equipement_localisation)',
                'C.libelle_atelier',
            ],
            [
                'R.id_utilisateur' => $idUtilisateur,
                'ORDER' => ['R.date_debut' => 'DESC'],
            ]
        );

        return is_array($rows) ? $rows : [];
    }

    public function hasConfirmedConflict(
        int $idEquipement,
        string $dateDebut,
        string $dateFin,
        int $excludeReservationId
    ): bool {
        return $this->database->has('RESERVATION', [
            'id_equipement' => $idEquipement,
            'statut' => 'CONFIRMEE',
            'id_reservation[!]' => $excludeReservationId,
            'date_debut[<]' => $dateFin,
            'date_fin[>]' => $dateDebut,
        ]);
    }

    public function updateStatut(int $idReservation, string $nouveauStatut): void
    {
        $this->database->update(
            'RESERVATION',
            ['statut' => $nouveauStatut],
            ['id_reservation' => $idReservation]
        );
    }
}