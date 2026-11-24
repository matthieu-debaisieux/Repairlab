<?php

declare(strict_types=1);

namespace App\Repository;

use Medoo\Medoo;

final class UserRepository
{
    public function __construct(private Medoo $database)
    {
    }

    public function findByEmail(string $email): ?array
    {
        $row = $this->database->get(
            'UTILISATEUR',
            [
                'id_utilisateur (id)',
                'nom (name)',
                'email',
                'mot_de_passe_hash (password_hash)',
                'role',
            ],
            ['email' => $email]
        );

        return is_array($row) ? $row : null;
    }
}
