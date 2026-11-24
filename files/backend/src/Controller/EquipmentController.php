<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\HttpInputException;
use App\Http\JsonInput;
use App\Http\JsonResponder;
use App\Http\ViewRenderer;
use App\Service\EquipmentService;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class EquipmentController
{
    public function __construct(
        private EquipmentService $service,
        private ViewRenderer $renderer
    ) {
    }

    public function index(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $filters = $request->getQueryParams();
        $error = null;
        $equipment = [];

        try {
            $equipment = $this->service->list(is_array($filters) ? $filters : []);
        } catch (InvalidArgumentException $exception) {
            $error = $exception->getMessage();
            $equipment = $this->service->list([]);
        }

        return $this->renderer->render($response, 'home', [
            'equipment' => $equipment,
            'filters' => [
                'q' => is_string($filters['q'] ?? null) ? trim($filters['q']) : '',
                'category' => is_string($filters['category'] ?? null) ? trim($filters['category']) : '',
                'status' => is_string($filters['status'] ?? null) ? strtoupper(trim($filters['status'])) : '',
            ],
            'error' => $error,
            'csrf' => $this->csrfToken(),
        ], $error !== null ? 422 : 200);
    }

    public function webCreate(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $data = $request->getParsedBody();
        $data = is_array($data) ? $data : [];

        $received = $data['csrf'] ?? null;
        $validToken = is_string($received)
            && hash_equals($this->csrfToken(), $received);

        if (!$validToken) {
            return $this->renderer->render($response, 'home', [
                'equipment' => $this->service->list(),
                'error' => 'Requête refusée : jeton CSRF absent ou invalide.',
                'csrf' => $this->csrfToken(),
            ], 403);
        }

        try {
            $this->service->create($data);
        } catch (InvalidArgumentException $exception) {
            return $this->renderer->render($response, 'home', [
                'equipment' => $this->service->list(),
                'error' => $exception->getMessage(),
                'csrf' => $this->csrfToken(),
            ], 422);
        }

        return $response
            ->withHeader('Location', '/')
            ->withStatus(303);
    }

    public function apiIndex(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $filters = $request->getQueryParams();

        try {
            $items = $this->service->list(is_array($filters) ? $filters : []);
            return JsonResponder::respond($response, $items);
        } catch (InvalidArgumentException $exception) {
            return JsonResponder::respond($response, [
                'error' => 'VALIDATION_ERROR',
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function apiShow(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $equipment = $this->service->get((int) ($args['id'] ?? 0));

        if ($equipment === null) {
            return JsonResponder::respond($response, [
                'error' => 'EQUIPMENT_NOT_FOUND',
                'message' => 'Équipement introuvable.',
            ], 404);
        }

        return JsonResponder::respond($response, $equipment);
    }

    public function apiCreate(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        try {
            $input = JsonInput::readObject($request);
            $equipment = $this->service->create($input);
        } catch (HttpInputException $exception) {
            return JsonResponder::respond($response, [
                'error' => 'INVALID_REQUEST',
                'message' => $exception->getMessage(),
            ], $exception->status);
        } catch (InvalidArgumentException $exception) {
            return JsonResponder::respond($response, [
                'error' => 'VALIDATION_ERROR',
                'message' => $exception->getMessage(),
            ], 422);
        }

        return JsonResponder::respond($response, $equipment, 201)
            ->withHeader('Location', '/api/equipment/' . $equipment['id']);
    }

    private function csrfToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_set_cookie_params([
                'path' => '/',
                'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }

        if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf'];
    }
}