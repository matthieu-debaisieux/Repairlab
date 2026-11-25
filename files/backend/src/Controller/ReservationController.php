<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\ReservationRefusee;
use App\Http\HttpInputException;
use App\Http\JsonInput;
use App\Http\JsonResponder;
use App\Http\Session;
use App\Service\ReservationService;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ReservationController
{
    private const DATE_FORMATS = ['Y-m-d H:i:s', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i'];

    public function __construct(
        private ReservationService $reservationService,
        private Session $session
    ) {
    }

    /** POST /api/reservations : { "equipmentId": 1, "debut": "...", "fin": "..." } */
    public function create(Request $request, Response $response): Response
    {
        $user = $this->session->user();
        if (!is_array($user)) {
            if ($this->wantsJson($request)) {
                return JsonResponder::respond($response, [
                    'error' => 'UNAUTHENTICATED',
                    'message' => 'Connexion requise.',
                ], 401);
            }

            $this->session->flash('error', 'Connexion requise.');
            return $this->redirect($response, '/login');
        }

        $userId = (int) ($user['id'] ?? $user['id_utilisateur'] ?? 0);
        $role = (string) ($user['role'] ?? '');

        if ($this->wantsJson($request)) {
            try {
                $input = JsonInput::readObject($request);
            } catch (HttpInputException $exception) {
                return JsonResponder::respond($response, [
                    'error' => 'INVALID_REQUEST',
                    'message' => $exception->getMessage(),
                ], $exception->status);
            }
        } else {
            $input = (array) ($request->getParsedBody() ?? []);
        }

        $equipmentId = $input['equipmentId'] ?? $input['id_equipement'] ?? null;
        $equipmentId = (is_int($equipmentId) || (is_string($equipmentId) && ctype_digit($equipmentId)))
            ? (int) $equipmentId
            : 0;

        $start = $this->parseDate($input['debut'] ?? $input['date_debut'] ?? null);
        $end = $this->parseDate($input['fin'] ?? $input['date_fin'] ?? null);

        if ($userId <= 0 || $equipmentId <= 0 || $start === null || $end === null) {
            if ($this->wantsJson($request)) {
                return JsonResponder::respond($response, [
                    'error' => 'VALIDATION_ERROR',
                    'message' => 'equipmentId (entier), debut et fin (AAAA-MM-JJ HH:MM:SS) sont obligatoires.',
                ], 422);
            }

            $this->session->flash('error', 'Veuillez renseigner un équipement et des dates valides.');
            return $this->redirect($response, '/equipements');
        }

        try {
            $created = $this->reservationService->creer(
                idEquipement: $equipmentId,
                idUtilisateur: $userId,
                roleUtilisateur: $role,
                dateDebut: $start,
                dateFin: $end
            );

            if ($this->wantsJson($request)) {
                return JsonResponder::respond($response, ['data' => $created], 201);
            }

            $this->session->flash('success', 'Réservation enregistrée.');
            return $this->redirect($response, '/mon-espace');
        } catch (ReservationRefusee $e) {
            if ($this->wantsJson($request)) {
                return JsonResponder::respond($response, [
                    'error'  => $e->getMessage(),
                    'reason' => $e->reason,
                ], $e->httpStatus);
            }

            $this->session->flash('error', $this->messageHtmlPourMotif($e->reason, $e->getMessage()));
            return $this->redirect($response, '/equipements');
        }
    }

    private function messageHtmlPourMotif(string $reason, string $fallback): string
    {
        return match ($reason) {
            'PANNE_BLOQUANTE'     => 'Réservation impossible : cette machine est actuellement en panne bloquante.',
            'ATELIER_PRIORITAIRE' => 'Réservation impossible : ce créneau est réservé à un atelier encadré.',
            'CRENEAU_OCCUPE',
            'CONFLIT_CRENEAU'     => 'Réservation impossible : un créneau confirmé occupe déjà cette plage horaire.',
            default               => $fallback,
        };
    }

    /** Accepte uniquement une date réelle et complète ; retourne le format SQL, ou null. */
    private function parseDate(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        foreach (self::DATE_FORMATS as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
            if ($date !== false && $date->format($format) === $value) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        return null;
    }

    public function mesCreneaux(Request $request, Response $response): Response
    {
        $currentUser = $this->session->user();
        if (!is_array($currentUser)) {
            return $this->wantsJson($request)
                ? $this->json($response, ['error' => 'Authentification requise.'], 401)
                : $this->redirect($response, '/login');
        }

        $userId = (int) ($currentUser['id'] ?? $currentUser['id_utilisateur'] ?? 0);
        $items = $this->reservationService->listerParUtilisateur($userId);

        return $this->json($response, ['data' => $items], 200);
    }

    public function cancel(Request $request, Response $response, array $args): Response
    {
        $currentUser = $this->session->user();
        if (!is_array($currentUser)) {
            return $this->wantsJson($request)
                ? $this->json($response, ['error' => 'Authentification requise.'], 401)
                : $this->redirect($response, '/login');
        }

        $isJson = $this->wantsJson($request);
        $body = (array) ($request->getParsedBody() ?? []);

        if (!$isJson && !$this->isValidCsrf($body['csrf'] ?? null)) {
            $this->session->flash('error', 'Requête refusée : jeton CSRF absent ou invalide.');
            return $this->redirect($response, '/mon-espace');
        }

        $idReservation = (int) ($args['id'] ?? 0);
        $userId = (int) ($currentUser['id'] ?? $currentUser['id_utilisateur'] ?? 0);
        $role   = (string) ($currentUser['role'] ?? '');

        try {
            $updated = $this->reservationService->annuler($idReservation, $userId, $role);

            if ($isJson) {
                return $this->json($response, ['data' => $updated], 200);
            }

            $this->session->flash('success', 'Réservation annulée.');
            return $this->redirect($response, '/mon-espace');
        } catch (ReservationRefusee $e) {
            if ($isJson) {
                return $this->json($response, [
                    'error'  => $e->getMessage(),
                    'reason' => $e->reason,
                ], $e->httpStatus);
            }

            $this->session->flash('error', $e->getMessage());
            return $this->redirect($response, '/mon-espace');
        }
    }

    public function confirm(Request $request, Response $response, array $args): Response
    {
        $currentUser = $this->session->user();
        if (!is_array($currentUser)) {
            return $this->wantsJson($request)
                ? $this->json($response, ['error' => 'Authentification requise.'], 401)
                : $this->redirect($response, '/login');
        }

        $isJson = $this->wantsJson($request);
        $body = (array) ($request->getParsedBody() ?? []);

        if (!$isJson && !$this->isValidCsrf($body['csrf'] ?? null)) {
            $this->session->flash('error', 'Requête refusée : jeton CSRF absent ou invalide.');
            return $this->redirect($response, '/technicien');
        }

        $idReservation = (int) ($args['id'] ?? 0);
        $role = (string) ($currentUser['role'] ?? '');

        try {
            $updated = $this->reservationService->confirmerDemande($idReservation, $role);

            if ($isJson) {
                return $this->json($response, ['data' => $updated], 200);
            }

            $this->session->flash('success', 'Demande de réservation confirmée.');
            return $this->redirect($response, '/technicien');
        } catch (ReservationRefusee $e) {
            if ($isJson) {
                return $this->json($response, [
                    'error'  => $e->getMessage(),
                    'reason' => $e->reason,
                ], $e->httpStatus);
            }

            $this->session->flash('error', $e->getMessage());
            return $this->redirect($response, '/technicien');
        }
    }

    private function wantsJson(Request $request): bool
    {
        $path = $request->getUri()->getPath();
        if (str_starts_with($path, '/api/')) {
            return true;
        }

        $accept = $request->getHeaderLine('Accept');
        $contentType = $request->getHeaderLine('Content-Type');

        return str_contains($contentType, 'application/json')
            || (str_contains($accept, 'application/json') && !str_contains($accept, 'text/html'));
    }

    private function isValidCsrf(mixed $received): bool
    {
        if (!is_string($received) || $received === '') {
            return false;
        }

        $expected = $this->session->csrfToken();
        return is_string($expected) && $expected !== '' && hash_equals($expected, $received);
    }

    private function redirect(Response $response, string $location): Response
    {
        return $response
            ->withHeader('Location', $location)
            ->withStatus(303);
    }
}
