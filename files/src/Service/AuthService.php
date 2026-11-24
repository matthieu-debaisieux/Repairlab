<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\UserRepository;

final class AuthService
{
    /**
     * Hash bcrypt quelconque, utilisé quand l'e-mail n'existe pas : le temps de
     * réponse reste comparable et on ne révèle pas quels comptes existent.
     */
    private const DUMMY_HASH = '$2y$12$nEJe6NNKYLl5yoKEAyzpWeibpHnAmwVF.PyMHytXQbwXIH.cloDHC';

    public function __construct(private UserRepository $users)
    {
    }

    /**
     * Retourne l'utilisateur (sans son hash) ou null. Le message d'erreur
     * est le même que l'e-mail ou le mot de passe soit faux.
     */
    public function attempt(mixed $email, mixed $password): ?array
    {
        // Un client peut envoyer email[] : vérifier le type avant tout traitement.
        if (!is_string($email) || !is_string($password)) {
            return null;
        }

        $email = trim($email);
        if ($email === '' || $password === '' || strlen($email) > 190 || strlen($password) > 200) {
            return null;
        }

        $user = $this->users->findByEmail($email);
        $hash = $user['password_hash'] ?? self::DUMMY_HASH;
        $valid = password_verify($password, $hash);

        if ($user === null || !$valid) {
            return null;
        }

        return [
            'id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'role' => (string) $user['role'],
        ];
    }
}
