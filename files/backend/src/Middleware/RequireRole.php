<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\JsonResponder;
use App\Http\Session;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * Contrôle d'accès côté serveur : à brancher sur une route ou un groupe.
 * - Sans rôle listé : il suffit d'être connecté.
 * - Non connecté : redirection vers /login (HTML) ou 401 JSON (API).
 * - Connecté mais rôle insuffisant : 403.
 */
final class RequireRole implements MiddlewareInterface
{
    /** @param string[] $roles */
    public function __construct(
        private Session $session,
        private array $roles = [],
        private bool $json = false
    ) {
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $user = $this->session->user();

        if ($user === null) {
            return $this->deny(401, 'UNAUTHENTICATED', 'Connexion requise.');
        }

        if ($this->roles !== [] && !in_array($user['role'], $this->roles, true)) {
            return $this->deny(403, 'FORBIDDEN', 'Droits insuffisants pour cette action.');
        }

        return $handler->handle($request);
    }

    private function deny(int $status, string $code, string $message): ResponseInterface
    {
        $response = new Response();

        if ($this->json) {
            return JsonResponder::respond($response, ['error' => $code, 'message' => $message], $status);
        }

        if ($status === 401) {
            return $response->withHeader('Location', '/login')->withStatus(303);
        }

        $response->getBody()->write('<h1>Accès interdit</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>');

        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withStatus($status);
    }
}
