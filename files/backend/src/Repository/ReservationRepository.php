<?php

declare(strict_types=1);

namespace App\Repository;

use Medoo\Medoo;
use PDO;
use RuntimeException;

final class ReservationRepository
{
    public function __construct(
        private readonly Medoo $db
    ) {}
    /**
     * Appel de la procédure stockée via la connexion PDO de Medoo.
     *
     * @return array{resultat: string, id_reservation: ?int}
     */
    public function creerReservation(
        int $idEquipement,
        int $idUtilisateur,
        string $dateDebut,
        string $dateFin,
        string $statutInitial,
        ?int $idCreneau
    ): array {
        $pdo = $this->db->pdo;

        $stmt = $pdo->prepare(
            'CALL sp_creer_reservation(:equipement, :utilisateur, :debut, :fin, :statut, :creneau, @resultat, @id_reservation)'
        );

        $stmt->bindValue(':equipement', $idEquipement, PDO::PARAM_INT);
        $stmt->bindValue(':utilisateur', $idUtilisateur, PDO::PARAM_INT);
        $stmt->bindValue(':debut', $dateDebut, PDO::PARAM_STR);
        $stmt->bindValue(':fin', $dateFin, PDO::PARAM_STR);
        $stmt->bindValue(':statut', $statutInitial, PDO::PARAM_STR);
        $stmt->bindValue(':creneau', $idCreneau, $idCreneau === null ? PDO::PARAM_NULL : PDO::PARAM_INT);

        $stmt->execute();
        $stmt->closeCursor();

        $row = $pdo->query('SELECT @resultat AS resultat, @id_reservation AS id_reservation')
            ->fetch(PDO::FETCH_ASSOC);

        if ($row === false || !isset($row['resultat'])) {
            throw new RuntimeException('Erreur lors de la lecture du résultat de sp_creer_reservation.');
        }

        return [
            'resultat' => (string) $row['resultat'],
            'id_reservation' => $row['id_reservation'] !== null ? (int) $row['id_reservation'] : null,
        ];
    }

    /* Requêtes courantes gérées directement avec Medoo.*/
    public function findByUtilisateur(int $idUtilisateur): array
    {
        return $this->db->select('RESERVATION', '*', [
            'id_utilisateur' => $idUtilisateur,
            'ORDER' => ['date_debut' => 'DESC'],
        ]);
    }

    public function updateStatut(int $idReservation, string $statut): int
    {
        $stmt = $this->db->update('RESERVATION', [
            'statut' => $statut,
        ], [
            'id_reservation' => $idReservation,
        ]);

        return $stmt->rowCount();
    }
}