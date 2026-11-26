<?php

declare(strict_types=1);

namespace App\Repository;

use Medoo\Medoo;
use Throwable;

final class IncidentRepository
{
    public function __construct(private Medoo $database)
    {
    }

    public function equipementExiste(int $idEquipement): bool
    {
        return $this->database->has('EQUIPEMENT', ['id_equipement' => $idEquipement]);
    }

    /** Crée l'incident à l'état OUVERT ; le trigger écrit la première ligne d'historique. */
    public function create(
        int $idEquipement,
        int $idAuteur,
        string $titre,
        string $description,
        bool $estTechnique
    ): int {
        $this->database->insert('INCIDENT', [
            'titre' => $titre,
            'description' => $description,
            'statut' => 'OUVERT',
            'est_technique' => $estTechnique ? 1 : 0,
            'est_bloquant' => 0,
            'id_equipement' => $idEquipement,
            'id_utilisateur' => $idAuteur,
        ]);

        return (int) $this->database->id();
    }

    public function find(int $id): ?array
    {
        $row = $this->database->get(
            'INCIDENT (I)',
            [
                '[>]EQUIPEMENT (E)' => ['id_equipement' => 'id_equipement'],
                '[>]UTILISATEUR (U)' => ['id_utilisateur' => 'id_utilisateur'],
            ],
            [
                'I.id_incident (id)',
                'I.titre (title)',
                'I.description',
                'I.statut (status)',
                'I.est_bloquant (blocking)',
                'I.est_technique (technical)',
                'I.date_signalement (reported_at)',
                'I.date_resolution (resolved_at)',
                'E.id_equipement (equipment_id)',
                'E.nom (equipment)',
                'U.id_utilisateur (author_id)',
                'U.nom (author)',
            ],
            ['I.id_incident' => $id]
        );

        if (!is_array($row)) {
            return null;
        }

        $row['blocking'] = (int) $row['blocking'] === 1;
        $row['technical'] = (int) $row['technical'] === 1;

        return $row;
    }

    /** Historique des changements d'état, du plus ancien au plus récent. */
    public function history(int $idIncident): array
    {
        return $this->database->select(
            'HISTORIQUE_INCIDENT (H)',
            ['[>]UTILISATEUR (U)' => ['id_utilisateur' => 'id_utilisateur']],
            [
                'H.ancien_etat (from)',
                'H.nouvel_etat (to)',
                'H.date_changement (at)',
                'H.motif (reason)',
                'U.nom (author)',
            ],
            ['H.id_incident' => $idIncident, 'ORDER' => ['H.id_historique' => 'ASC']]
        );
    }

    /**
     * Change l'état. L'UPDATE n'a lieu que si l'état courant est toujours celui qui a été lu
     * Retourne false si l'état a changé entre-temps.
     * Les variables de session permettent au trigger d'écrire l'auteur et le motif dans l'historique.
     */
    public function changeStatus(int $id, string $expectedStatus, string $newStatus, int $userId, ?string $reason): bool
    {
        $pdo = $this->database->pdo;
        $pdo->beginTransaction();

        try {
            $set = $pdo->prepare('SET @app_user_id = :u, @motif_changement = :m');
            $set->execute([':u' => $userId, ':m' => $reason]);

            $statement = $this->database->update(
                'INCIDENT',
                [
                    'statut' => $newStatus,
                    'date_resolution' => in_array($newStatus, ['RESOLU', 'CLOTURE'], true)
                        ? date('Y-m-d H:i:s')
                        : null,
                ],
                ['id_incident' => $id, 'statut' => $expectedStatus]
            );
            $changed = $statement->rowCount() === 1;

            $pdo->exec('SET @app_user_id = NULL, @motif_changement = NULL');
            $pdo->commit();

            return $changed;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $pdo->exec('SET @app_user_id = NULL, @motif_changement = NULL');
            throw $e;
        }
    }

    public function setBlocking(int $id, bool $blocking): void
    {
        $this->database->update('INCIDENT', ['est_bloquant' => $blocking ? 1 : 0], ['id_incident' => $id]);
    }
}