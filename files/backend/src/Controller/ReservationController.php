<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\ReservationRefusee;
use App\Http\HttpInputException;
use App\Http\JsonInput;
use App\Http\JsonResponder;
use App\Http\Session;
use App\Http\ViewRenderer;
use App\Repository\IncidentRepository;
use App\Service\ReservationService;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Le même contrôleur sert le formulaire HTML et l'API JSON, mais une seule décision :
 * celle de ReservationService. Le contrôleur ne fait que traduire le résultat.
 * - HTML : message flash (texte du motif) et redirection ; jeton CSRF obligatoire.
 * - API (/api/...) : JSON {"error": "<MOTIF>", "message": "..."} et statut HTTP du motif.
 */
final class ReservationController
{
    private const DATE_FORMATS = ['Y-m-d H:i:s', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i'];

    public function __construct(
        private ReservationService $reservationService,
        private Session $session,
        private ?viewRenderer $renderer = null,
        private ?IncidentRepository $incidentRepository = null
    ) {
    }

    /** GET /mon-espace : page HTML des réservations et liens d'incidents. */
    public function monEspace(Request $request, Response $response): Response
    {
        $user = $this->session->user();
        if (!is_array($user)) {
            $this->session->flash('error', 'Connectez-vous pour accéder à votre espace.');

            return $this->redirect($response, '/login');
        }

        $userId = (int) $user['id'];
        $reservations = $this->reservationService->listerParUtilisateur($userId);

        $incidents = [];
        if ($this->incidentRepository !== null && method_exists($this->incidentRepository, 'listForUser')) {
            $incidents = $this->incidentRepository->listForUser($userId, (string) ($user['role'] ?? 'ADHERENT'));
        }

        if ($this->renderer === null) {
            return $this->json($response, [
                'user' => $user,
                'reservations' => $reservations,
                'incidents' => $incidents,
            ]);
        }

        return $this->renderer->render($response, 'mon-espace', [
            'user' => $user,
            'reservations' => $reservations,
            'incidents' => $incidents,
            'csrf' => $this->session->csrfToken(),
            'flashError' => $this->session->pullFlash('error'),
            'flashSuccess' => $this->session->pullFlash('success'),
        ]);
    }

    /** POST /reservations (formulaire) ou POST /api/reservations : equipmentId, debut, fin. */
    public function create(Request $request, Response $response): Response
    {
        $json = $this->wantsJson($request);
        $user = $this->session->user();
        if (!is_array($user)) {
            if ($json) {
                return $this->error($response, 'UNAUTHENTICATED', 'Connexion requise.', 401);
            }

            $this->session->flash('error', 'Connectez-vous pour réserver une machine.');

            return $this->redirect($response, '/login');
        }

        if ($json) {
            try {
                $input = JsonInput::readObject($request);
            } catch (HttpInputException $exception) {
                return $this->error($response, 'INVALID_REQUEST', $exception->getMessage(), $exception->status);
            }
        } else {
            $input = $request->getParsedBody();
            $input = is_array($input) ? $input : [];

            if (!$this->isValidCsrf($input['csrf'] ?? null)) {
                $this->session->flash('error', 'Requête refusée : jeton CSRF absent ou invalide.');

                return $this->redirect($response, '/equipements');
            }
        }

        $equipmentId = $input['equipmentId'] ?? null;
        $equipmentId = (is_int($equipmentId) || (is_string($equipmentId) && ctype_digit($equipmentId)))
            ? (int) $equipmentId
            : 0;

        $start = $this->parseDate($input['debut'] ?? null);
        $end = $this->parseDate($input['fin'] ?? null);
        $back = $equipmentId > 0 ? '/equipements/' . $equipmentId : '/equipements';

        if ($equipmentId <= 0 || $start === null || $end === null) {
            $message = 'equipmentId (entier), debut et fin (AAAA-MM-JJ HH:MM:SS) sont obligatoires.';
            if ($json) {
                return $this->error($response, 'VALIDATION_ERROR', $message, 422);
            }

            $this->session->flash('error', 'Veuillez choisir une date de début et une date de fin valides.');

            return $this->redirect($response, $back);
        }

        try {
            $created = $this->reservationService->creer(
                idEquipement: $equipmentId,
                idUtilisateur: (int) $user['id'],
                roleUtilisateur: (string) $user['role'],
                dateDebut: $start,
                dateFin: $end
            );
        } catch (ReservationRefusee $refus) {
            if ($json) {
                return $this->error($response, $refus->reason, $refus->getMessage(), $refus->httpStatus);
            }

            $this->session->flash('error', $refus->getMessage());

            return $this->redirect($response, $back);
        } catch (InvalidArgumentException $exception) {
            if ($json) {
                return $this->error($response, 'VALIDATION_ERROR', $exception->getMessage(), 422);
            }

            $this->session->flash('error', $exception->getMessage());

            return $this->redirect($response, $back);
        }

        if ($json) {
            return $this->json($response, ['data' => $created], 201);
        }

        $this->session->flash('success', 'Réservation confirmée.');

        return $this->redirect($response, '/mon-espace');
    }

    /** GET /api/reservations : réservations de l'utilisateur connecté. */
    public function mesCreneaux(Request $request, Response $response): Response
    {
        $user = $this->session->user();
        if ($user === null) {
            return $this->error($response, 'UNAUTHENTICATED', 'Connexion requise.', 401);
        }

        return $this->json($response, [
            'data' => $this->reservationService->listerParUtilisateur((int) $user['id']),
        ]);
    }

    public function cancel(Request $request, Response $response, array $args): Response
    {
        $json = $this->wantsJson($request);
        $user = $this->session->user();
        if ($user === null) {
            return $json
                ? $this->error($response, 'UNAUTHENTICATED', 'Connexion requise.', 401)
                : $this->redirect($response, '/login');
        }

        if (!$json && !$this->isValidCsrf($this->bodyValue($request, 'csrf'))) {
            $this->session->flash('error', 'Requête refusée : jeton CSRF absent ou invalide.');

            return $this->redirect($response, '/mon-espace');
        }

        try {
            $updated = $this->reservationService->annuler(
                (int) ($args['id'] ?? 0),
                (int) $user['id'],
                (string) $user['role']
            );
        } catch (ReservationRefusee $refus) {
            if ($json) {
                return $this->error($response, $refus->reason, $refus->getMessage(), $refus->httpStatus);
            }

            $this->session->flash('error', $refus->getMessage());

            return $this->redirect($response, '/mon-espace');
        }

        if ($json) {
            return $this->json($response, ['data' => $updated]);
        }

        $this->session->flash('success', 'Réservation annulée.');

        return $this->redirect($response, '/mon-espace');
    }

    public function confirm(Request $request, Response $response, array $args): Response
    {
        $json = $this->wantsJson($request);
        $user = $this->session->user();

        if ($user === null) {
            return $json
                ? $this->error($response, 'UNAUTHENTICATED', 'Connexion requise.', 401)
                : $this->redirect($response, '/login');
        }

        if (!$json && !$this->isValidCsrf($this->bodyValue($request, 'csrf'))) {
            $this->session->flash('error', 'Requête refusée : jeton CSRF absent ou invalide.');

            return $this->redirect($response, '/technicien');
        }

        try {
            $updated = $this->reservationService->confirmerDemande((int) ($args['id'] ?? 0), (string) $user['role']);
        } catch (ReservationRefusee $refus) {
            if ($json) {
                return $this->error($response, $refus->reason, $refus->getMessage(), $refus->httpStatus);
            }

            $this->session->flash('error', $refus->getMessage());

            return $this->redirect($response, '/technicien');
        }

        if ($json) {
            return $this->json($response, ['data' => $updated]);
        }

        $this->session->flash('success', 'Demande de réservation confirmée.');

        return $this->redirect($response, '/technicien');
    }

    /**
     * API ou non : décidé uniquement par l'adresse. Se fier à l'en-tête Accept permettrait de
     * contourner le contrôle CSRF des routes HTML.
     */
    private function wantsJson(Request $request): bool
    {
        return str_starts_with($request->getUri()->getPath(), '/api/');
    }

    private function bodyValue(Request $request, string $key): mixed
    {
        $body = $request->getParsedBody();

        return is_array($body) ? ($body[$key] ?? null) : null;
    }

    private function isValidCsrf(mixed $received): bool
    {
        return is_string($received) && $received !== '' && hash_equals($this->session->csrfToken(), $received);
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

    private function json(Response $response, array $payload, int $status = 200): Response
    {
        return JsonResponder::respond($response, $payload, $status);
    }

    private function error(Response $response, string $code, string $message, int $status): Response
    {
        return $this->json($response, ['error' => $code, 'message' => $message], $status);
    }

    private function redirect(Response $response, string $location): Response
    {
        return $response->withHeader('Location', $location)->withStatus(303);
    }
}