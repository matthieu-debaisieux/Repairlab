<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Session;
use App\Http\ViewRenderer;
use App\Service\AuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AuthController
{
    public function __construct(
        private AuthService $auth,
        private Session $session,
        private ViewRenderer $renderer
    ) {
    }

    public function showLogin(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        if ($this->session->user() !== null) {
            return $this->redirect($response, '/mon-espace');
        }

        return $this->renderLogin($response, null, '');
    }

    public function login(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $data = $request->getParsedBody();
        $data = is_array($data) ? $data : [];

        if (!$this->session->checkCsrf($data['csrf'] ?? null)) {
            return $this->renderLogin($response, 'Requête refusée : jeton CSRF absent ou invalide.', '', 403);
        }

        $user = $this->auth->attempt($data['email'] ?? null, $data['password'] ?? null);

        if ($user === null) {
            // Même message que l'e-mail ou le mot de passe soit faux.
            $email = is_string($data['email'] ?? null) ? $data['email'] : '';

            return $this->renderLogin($response, 'Identifiants incorrects.', $email, 401);
        }

        $this->session->login($user);

        // Redirection 303 : un rafraîchissement ne renvoie pas le formulaire.
        return $this->redirect($response, '/mon-espace');
    }

    public function logout(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $data = $request->getParsedBody();
        $data = is_array($data) ? $data : [];

        if (!$this->session->checkCsrf($data['csrf'] ?? null)) {
            $response->getBody()->write('Requête refusée : jeton CSRF absent ou invalide.');

            return $response->withStatus(403);
        }

        $this->session->logout();

        return $this->redirect($response, '/login');
    }

    public function espace(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        return $this->renderer->render($response, 'espace', [
            'title' => 'Mon espace',
            'user' => $this->session->user(),
            'csrf' => $this->session->csrfToken(),
        ]);
    }

    public function technicien(
    ServerRequestInterface $request,
    ResponseInterface $response
    ): ResponseInterface {
    $dashboard = $this->dashboardService->getDashboardData();

    return $this->renderer->render($response, 'espace', [
        'title' => 'Tableau de bord technicien',
        'user' => $this->session->user(),
        'csrf' => $this->session->csrfToken(),
        'dashboard' => $dashboard,
    ]);
}

    private function renderLogin(
        ResponseInterface $response,
        ?string $error,
        string $email,
        int $status = 200
    ): ResponseInterface {
        return $this->renderer->render($response, 'login', [
            'error' => $error,
            'email' => $email,
            'csrf' => $this->session->csrfToken(),
        ], $status);
    }

    private function redirect(ResponseInterface $response, string $to): ResponseInterface
    {
        return $response->withHeader('Location', $to)->withStatus(303);
    }
}
