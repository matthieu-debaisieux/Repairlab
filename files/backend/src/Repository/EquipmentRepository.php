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
            'E.visible_public' => 1,
            'ORDER' => ['E.id_equipement' => 'DESC'],
        ];

        $q = $filters['q'] ?? '';
        if (is_string($q) && $q !== '') {
            $where['OR'] = [
                'E.nom[~]' => $q,
                'E.localisation[~]' => $q,
            ];
        }

        $category = $filters['category'] ?? '';
        if (is_string($category) && $category !== '') {
            if (ctype_digit($category)) {
                $where['E.id_categorie'] = (int) $category;
            } else {
                $where['C.libelle'] = $category;
            }
        }

        $status = $filters['status'] ?? '';
        if (is_string($status) && $status !== '') {
            $where['E.statut_technique'] = $status;
        }

        $rows = $this->database->select(
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

    public function create(string $name, string $status): array
    {
        $this->database->insert('EQUIPEMENT', [
            'nom' => $name,
            'statut_technique' => $status,
            'visible_public' => 1,
        ]);

        $id = (int) $this->database->id();

        return $this->find($id) ?? [
            'id' => $id,
            'name' => $name,
            'status' => $status,
        ];
    }
}