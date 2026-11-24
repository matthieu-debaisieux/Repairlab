<?php

declare(strict_types=1);

namespace App\Http;

final class Session
{
    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        session_set_cookie_params([
            'path' => '/',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    /** Ne mémorise que l'identité minimale : identifiant, nom, rôle. */
    public function login(array $user): void
    {
        $this->start();
        // Nouvel identifiant de session à la connexion : évite la fixation de session.
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'role' => (string) $user['role'],
        ];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    public function user(): ?array
    {
        $this->start();
        $user = $_SESSION['user'] ?? null;

        return is_array($user) ? $user : null;
    }

    public function logout(): void
    {
        $this->start();
        $_SESSION = [];

        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $params['path'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?: 'Lax',
        ]);

        session_destroy();
    }

    public function csrfToken(): string
    {
        $this->start();

        if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf'];
    }

    public function checkCsrf(mixed $received): bool
    {
        return is_string($received) && hash_equals($this->csrfToken(), $received);
    }
}
