<?php

declare(strict_types=1);

use App\Controller\AuthController;
use App\Controller\ReservationController;
use App\Http\Session;
use App\Middleware\RequireRole;
use Slim\App;

return static function (
    App $app,
    AuthController $controller,
    Session $session,
    ReservationController $reservations
): void {
    $app->get('/login', [$controller, 'showLogin']);
    $app->post('/login', [$controller, 'login']);
    $app->post('/logout', [$controller, 'logout']);

    // Toute personne connectée.
    $app->get('/mon-espace', [$reservations, 'monEspace'])
        ->add(new RequireRole($session));

    // Rôles autorisés : technicien et administrateur.
    $app->get('/technicien', [$controller, 'technicien'])
        ->add(new RequireRole($session, ['TECHNICIEN', 'ADMIN']));
};