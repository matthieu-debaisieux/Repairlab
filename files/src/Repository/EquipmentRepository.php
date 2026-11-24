<?php

declare(strict_types=1);

namespace App\Repository;

use Medoo\Medoo;

final class EquipmentRepository
{
    public function __construct(private Medoo $database)
    {
    }

    public function all(): array
    {
        return $this->database->select(
            'EQUIPEMENT (E)',
            ['[>]CATEGORIE_EQUIPEMENT (C)' => ['id_categorie' => 'id_categorie']],
            ['E.id_equipement (id)', 'E.nom (name)', 'E.statut_technique (status)', 'E.localisation', 'E.controle_requis', 'C.libelle (category)'],
            ['E.visible_public' => 1, 'ORDER' => ['E.id_equipement' => 'DESC']]
        );
    }

    public function find(int $id): ?array
    {
        $row = $this->database->get(
            'EQUIPEMENT (E)',
            ['[>]CATEGORIE_EQUIPEMENT (C)' => ['id_categorie' => 'id_categorie']],
            ['E.id_equipement (id)', 'E.nom (name)', 'E.statut_technique (status)', 'C.libelle (category)'],
            ['E.id_equipement' => $id, 'E.visible_public' => 1]
        );

        return is_array($row) ? $row : null;
    }

    public function create(string $name, string $status): array
    {
        $this->database->insert('EQUIPEMENT', [
            'nom (name)' => $name,
            'statut_technique (status)' => $status,
        ]);

        $id = (int) $this->database->id();

        return $this->find($id) ?? [
            'id_equipement (id)' => $id,
            'nom (name)' => $name,
            'statut_technique (status)' => $status,
        ];
    }
}
