<?php

declare(strict_types=1);

namespace App\Repository;

use Medoo\Medoo;

final class CreneauPrioritaireRepository
{
    public function __construct(
        private readonly Medoo $db
    ) {}
    /**
     * Retourne le premier créneau prioritaire chevauchant la plage demandée, ou null.
     *
     * @return array<string, mixed>|null
     */
    public function findChevauchant(int $idEquipement, string $dateDebut, string $dateFin): ?array
    {
        $row = $this->db->get('CRENEAU_PRIORITAIRE', [
            'id_creneau',
            'date_debut',
            'date_fin',
            'libelle_atelier',
            'id_equipement',
        ], [
            'AND' => [
                'id_equipement' => $idEquipement,
                'date_debut[<]' => $dateFin,
                'date_fin[>]' => $dateDebut,
            ],
        ]);

        return is_array($row) ? $row : null;
    }
}