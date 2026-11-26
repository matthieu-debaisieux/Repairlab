<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\IncidentRefuse;
use App\Http\HttpInputException;
use App\Http\JsonInput;
use App\Http\JsonResponder;
use App\Http\Session;
use App\Http\ViewRenderer;
use App\Service\IncidentService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Contrôleur unique pour le HTML et l'API JSON des incidents (R6 / R3).
 * Toute décision métier est déléguée à IncidentService.
 */
final class IncidentController
{
    public function __construct(
        private IncidentService $incidentService,
        private Session $session,
        private ViewRenderer $renderer
    ) {
    }

    /** GET /incidents/{id} (HTML) ou GET /api/incidents/{id} (JSON). */
    public function show(Request $request, Response $response, array $args): Response
    {
        $json = $this->wantsJson($request);
        $user = $this->session->user();

        if ($user === null) {
            if ($json) {
                return $this->error($response, 'UNAUTHENTICATED', 'Connexion requise.', 401);
            }
            $this->session->flash('error', 'Connectez-vous pour consulter un incident.');

            return $this->redirect($response, '/login');
        }

        $id = (int) ($args['id'] ?? 0);

        try {
            $data = $this->incidentService->consulter(
                $id,
                (int) $user['id'],
                (string) $user['role']
            );
        } catch (IncidentRefuse $refus) {
            if ($json) {
                return $this->error($response, $refus->reason, $refus->getMessage(), $refus->httpStatus);
            }
            $this->session->flash('error', $refus->getMessage());

            return $this->redirect($response, '/mon-espace');
        }

        if ($json) {
            return $this->json($response, ['data' => $data], 200);
        }

        return $this->renderer->render($response, 'incident', [
            'incident' => $data['incident'],
            'history' => $data['history'],
            'actions' => $data['actions'],
            'user' => $user,
            'csrf' => $this->session->csrfToken(),
            'flashError' => $this->session->pullFlash('error'),
            'flashSuccess' => $this->session->pullFlash('success'),
        ]);
    }

    /** POST /incidents (formulaire) ou POST /api/incidents (JSON). */
    public function create(Request $request, Response $response): Response
    {
        $json = $this->wantsJson($request);
        $user = $this->session->user();

        if ($user === null) {
            if ($json) {
                return $this->error($response, 'UNAUTHENTICATED', 'Connexion requise.', 401);
            }
            $this->session->flash('error', 'Connectez-vous pour signaler un incident.');

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
        $titre = is_string($input['titre'] ?? null) ? $input['titre'] : '';
        $description = is_string($input['description'] ?? null) ? $input['description'] : '';
        $nature = is_string($input['nature'] ?? null) ? strtoupper(trim($input['nature'])) : 'TECHNIQUE';
        $back = $equipmentId > 0 ? '/equipements/' . $equipmentId : '/equipements';

        if ($equipmentId <= 0) {
            if ($json) {
                return $this->error($response, 'VALIDATION_ERROR', 'equipmentId invalide.', 422);
            }
            $this->session->flash('error', 'Équipement invalide.');

            return $this->redirect($response, '/equipements');
        }

        try {
            $created = $this->incidentService->signaler(
                idUtilisateur: (int) $user['id'],
                idEquipement: $equipmentId,
                titre: $titre,
                description: $description,
                nature: $nature
            );
        } catch (IncidentRefuse $refus) {
            if ($json) {
                return $this->error($response, $refus->reason, $refus->getMessage(), $refus->httpStatus);
            }
            $this->session->flash('error', $refus->getMessage());

            return $this->redirect($response, $back);
        }

        if ($json) {
            return $this->json($response, ['data' => $created], 201);
        }

        $this->session->flash('success', 'Incident signalé.');

        return $this->redirect($response, '/incidents/' . (int) ($created['id'] ?? 0));
    }

    /** POST /incidents/{id}/transition ou POST /api/incidents/{id}/transition. */
    public function transition(Request $request, Response $response, array $args): Response
    {
        $json = $this->wantsJson($request);
        $user = $this->session->user();

        if ($user === null) {
            if ($json) {
                return $this->error($response, 'UNAUTHENTICATED', 'Connexion requise.', 401);
            }
            $this->session->flash('error', 'Connexion requise.');

            return $this->redirect($response, '/login');
        }

        $id = (int) ($args['id'] ?? 0);
        $back = $id > 0 ? '/incidents/' . $id : '/technicien';

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

                return $this->redirect($response, $back);
            }
        }

        $nouvelEtat = is_string($input['nouvelEtat'] ?? null)
            ? strtoupper(trim($input['nouvelEtat']))
            : '';
        $motif = isset($input['motif']) && is_string($input['motif']) ? $input['motif'] : null;

        try {
            $updated = $this->incidentService->changerEtat(
                id: $id,
                nouvelEtat: $nouvelEtat,
                motif: $motif,
                idUtilisateur: (int) $user['id'],
                role: (string) $user['role']
            );
        } catch (IncidentRefuse $refus) {
            if ($json) {
                return $this->error($response, $refus->reason, $refus->getMessage(), $refus->httpStatus);
            }
            $this->session->flash('error', $refus->getMessage());

            return $this->redirect($response, $back);
        }

        if ($json) {
            return $this->json($response, ['data' => $updated], 200);
        }

        $this->session->flash('success', 'État de l\'incident mis à jour (' . $nouvelEtat . ').');

        return $this->redirect($response, $back);
    }

    /** POST /incidents/{id}/bloquant ou POST /api/incidents/{id}/bloquant (R3). */
    public function toggleBlocking(Request $request, Response $response, array $args): Response
    {
        $json = $this->wantsJson($request);
        $user = $this->session->user();

        if ($user === null) {
            if ($json) {
                return $this->error($response, 'UNAUTHENTICATED', 'Connexion requise.', 401);
            }
            $this->session->flash('error', 'Connexion requise.');

            return $this->redirect($response, '/login');
        }

        $id = (int) ($args['id'] ?? 0);
        $back = $id > 0 ? '/incidents/' . $id : '/technicien';

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

                return $this->redirect($response, $back);
            }
        }

        $rawBlocking = $input['bloquant'] ?? null;
        $bloquant = match (true) {
            is_bool($rawBlocking) => $rawBlocking,
            is_int($rawBlocking) => $rawBlocking === 1,
            is_string($rawBlocking) => in_array(strtolower(trim($rawBlocking)), ['1', 'true', 'oui', 'on'], true),
            default => false,
        };

        try {
            $updated = $this->incidentService->definirBloquant($id, $bloquant, (string) $user['role']);
        } catch (IncidentRefuse $refus) {
            if ($json) {
                return $this->error($response, $refus->reason, $refus->getMessage(), $refus->httpStatus);
            }
            $this->session->flash('error', $refus->getMessage());

            return $this->redirect($response, $back);
        }

        if ($json) {
            return $this->json($response, ['data' => $updated], 200);
        }

        $this->session->flash('success', $bloquant ? 'Incident qualifié en panne bloquante.' : 'Blocage levé sur l\'incident.');

        return $this->redirect($response, $back);
    }

    private function wantsJson(Request $request): bool
    {
        return str_starts_with($request->getUri()->getPath(), '/api/');
    }

    private function isValidCsrf(mixed $received): bool
    {
        return is_string($received) && $received !== '' && hash_equals($this->session->csrfToken(), $received);
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