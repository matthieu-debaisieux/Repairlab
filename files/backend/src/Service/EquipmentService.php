<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\EquipmentRepository;
use InvalidArgumentException;

final class EquipmentService
{
    private const ALLOWED_STATUSES = [
        'EN_SERVICE',
        'HORS_SERVICE',
    ];

    public function __construct(private EquipmentRepository $repository)
    {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters = []): array
    {
        $q = '';
        if (isset($filters['q']) && is_string($filters['q'])) {
            $q = trim($filters['q']);
        }

        $category = '';
        if (isset($filters['category']) && is_string($filters['category'])) {
            $category = trim($filters['category']);
        } elseif (isset($filters['category']) && is_int($filters['category']) && $filters['category'] > 0) {
            $category = (string) $filters['category'];
        }

        $status = '';
        if (isset($filters['status']) && is_string($filters['status'])) {
            $trimmedStatus = strtoupper(trim($filters['status']));
            if ($trimmedStatus !== '') {
                if (!in_array($trimmedStatus, self::ALLOWED_STATUSES, true)) {
                    throw new InvalidArgumentException('Le statut fourni est invalide.');
                }
                $status = $trimmedStatus;
            }
        }

        return $this->repository->all([
            'q' => $q,
            'category' => $category,
            'status' => $status,
        ]);
    }

    public function get(int $id): ?array
    {
        return $this->repository->find($id);
    }

    public function create(array $input): array
    {
        $rawName = $input['name'] ?? null;
        if (!is_string($rawName)) {
            throw new InvalidArgumentException('Le nom doit être une chaîne.');
        }

        $name = trim($rawName);
        if ($name === '' || mb_strlen($name, 'UTF-8') > 150) {
            throw new InvalidArgumentException(
                'Nom obligatoire, 150 caractères maximum.'
            );
        }

        $rawStatus = $input['status'] ?? 'EN_SERVICE';
        if (!is_string($rawStatus)) {
            throw new InvalidArgumentException('Le statut doit être une chaîne.');
        }

        $status = strtoupper(trim($rawStatus));
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new InvalidArgumentException('Le statut fourni est invalide.');
        }

        return $this->repository->create($name, $status);
    }
}