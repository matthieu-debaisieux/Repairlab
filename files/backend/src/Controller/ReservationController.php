<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Domain\ReservationRefusee;
use App\Http\Session;
use App\Service\ReservationService;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ReservationController
{
    public function __construct(
        private readonly ReservationService $reservationService,
        private readonly Session $session
    ) {}

    public function create(Request $request, Response $response): Response
    {
        $currentUser = $this->resolveUser($request);

        if ($currentUser === null) {
            return $this->json($response, [
                'error' => 'Authentification requise.',
            ], 401);
        }

        $data = (array) ($request->getParsedBody() ?? []);

        $idEquipement = isset($data['id_equipement']) ? filter_var($data['id_equipement'], FILTER_VALIDATE_INT) : false;
        $dateDebut    = isset($data['date_debut']) && is_string($data['date_debut']) ? trim($data['date_debut']) : '';
        $dateFin      = isset($data['date_fin']) && is_string($data['date_fin']) ? trim($data['date_fin']) : '';

        if ($idEquipement === false || $idEquipement <= 0 || $dateDebut === '' || $dateFin === '') {
            return $this->json($response, [
                'error' => 'Les champs id_equipement, date_debut et date_fin sont requis.',
                'code'  => 'DONNEES_INCOMPLETES',
            ], 422);
        }

        try {
            $reservation = $this->reservationService->creer(
                idEquipement: (int) $idEquipement,
                idUtilisateur: (int) ($currentUser['id'] ?? $currentUser['id_utilisateur'] ?? 0),
                roleUtilisateur: (string) ($currentUser['role'] ?? ''),
                dateDebut: $dateDebut,
                dateFin: $dateFin
            );

            return $this->json($response, [
                'data' => $reservation,
            ], 201);
        } catch (ReservationRefusee $e) {
            return $this->json($response, [
                'error'  => $e->getMessage(),
                'reason' => $e->reason,
            ], $e->httpStatus);
        } catch (InvalidArgumentException $e) {
            return $this->json($response, [
                'error' => $e->getMessage(),
                'code'  => 'DONNEES_INVALIDES',
            ], 422);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveUser(Request $request): ?array
    {
        $sessionUser = $this->session->user();
        if (is_array($sessionUser)) {
            return $sessionUser;
        }

        $attrUser = $request->getAttribute('user');
        return is_array($attrUser) ? $attrUser : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function json(Response $response, array $payload, int $status): Response
    {
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_UNICODE));

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status);
    }
}