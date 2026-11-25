<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\JsonResponder;
use App\Http\Session;
use App\Http\ViewRenderer;
use App\Service\EquipmentService;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class EquipmentController
{
    public function __construct(
        private EquipmentService $service,
        private ViewRenderer $renderer,
        private Session $session
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

        $flashError = $this->session->pullFlash('error');
        $flashSuccess = $this->session->pullFlash('success');

        return $this->renderer->render($response, 'home', [
            'equipment' => $equipment,
            'filters' => [
                'q' => is_string($filters['q'] ?? null) ? trim($filters['q']) : '',
                'category' => is_string($filters['category'] ?? null) ? trim($filters['category']) : '',
                'status' => is_string($filters['status'] ?? null) ? strtoupper(trim($filters['status'])) : '',
            ],
            'error' => $error,
            'flashError' => $flashError,
            'flashSuccess' => $flashSuccess,
        ], $error !== null ? 422 : 200);
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
}
