<?php

declare(strict_types=1);

namespace App\Repository;

use Medoo\Medoo;

final class EquipmentRepository
{
    public function __construct(private Medoo $database)
    {
    }

    /**
     * @param array{q?: string, category?: string, status?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function all(array $filters = []): array
    {
        $where = [
            'visible_public' => 1,
            'ORDER' => ['id_equipement' => 'DESC'],
        ];

        $q = $filters['q'] ?? '';
        if (is_string($q) && $q !== '') {
            $where['OR'] = [
                'nom[~]' => $q,
                'localisation[~]' => $q,
            ];
        }

        $category = $filters['category'] ?? '';
        if (is_string($category) && $category !== '') {
            if (ctype_digit($category)) {
                $where['id_categorie'] = (int) $category;
            } else {
                $where['categorie_libelle'] = $category;
            }
        }

        $status = $filters['status'] ?? '';
        if (is_string($status) && $status !== '') {
            $where['statut_technique'] = $status;
        }

        $rows = $this->database->select(
            'v_equipements_catalogue',
            [
                'id_equipement (id)',
                'nom (name)',
                'statut_technique (status)',
                'localisation',
                'controle_requis',
                'categorie_libelle (category)',
                'incidents_actifs',
                'a_panne_bloquante',
                'reservations_a_venir',
            ],
            $where
        );

        return is_array($rows) ? $rows : [];
    }

    public function find(int $id): ?array
    {
        $row = $this->database->get(
            'EQUIPEMENT (E)',
            ['[>]CATEGORIE_EQUIPEMENT (C)' => ['id_categorie' => 'id_categorie']],
            [
                'E.id_equipement (id)',
                'E.nom (name)',
                'E.statut_technique (status)',
                'E.localisation',
                'E.controle_requis',
                'C.libelle (category)',
            ],
            ['E.id_equipement' => $id, 'E.visible_public' => 1]
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function categories(): array
    {
        return $this->database->select('CATEGORIE_EQUIPEMENT', [
            'id_categorie',
            'libelle',
        ], [
            'ORDER' => [
                'libelle' => 'ASC',
            ],
        ]);
    }
}
