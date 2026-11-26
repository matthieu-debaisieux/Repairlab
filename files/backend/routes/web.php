<?php

declare(strict_types=1);

use App\Controller\EquipmentController;
use App\Controller\ReservationController;
use App\Controller\IncidentController;
use App\Http\Session;
use App\Middleware\RequireRole;
use Slim\App;

return static function (
    App $app,
    EquipmentController $controller,
    ReservationController $reservations,
    IncidentController $incidents,
    Session $session
): void {
    // Routes GET
    $app->get('/', [$controller, 'index']);
    
    $app->get('/equipements', [$controller, 'index']);

    $app->get('/equipements/{id:[0-9]+}', [$controller, 'show']);

    $app->get('/incidents/{id:[0-9]+}', [$incidents, 'show'])
        ->add(new RequireRole($session, ['ADHERENT', 'TECHNICIEN', 'ADMIN']));
    
        // Routes POST
    $app->post('/technicien/reservations/{id:[0-9]+}/confirmer', [$reservations, 'confirm'])
        ->add(new RequireRole($session, ['TECHNICIEN', 'ADMIN']));

    $app->post('/reservations/{id:[0-9]+}/annuler', [$reservations, 'cancel'])
        ->add(new RequireRole($session, ['ADHERENT', 'TECHNICIEN', 'ADMIN']));

    $app->post('/reservations', [$reservations, 'create'])
        ->add(new RequireRole($session, ['ADHERENT', 'TECHNICIEN', 'ADMIN']));

    $app->post('/incidents', [$incidents, 'create'])
        ->add(new RequireRole($session, ['ADHERENT', 'TECHNICIEN', 'ADMIN']));

    $app->post('/incidents/{id:[0-9]+}/transition', [$incidents, 'transition'])
        ->add(new RequireRole($session, ['TECHNICIEN', 'ADMIN']));

    $app->post('/incidents/{id:[0-9]+}/bloquant', [$incidents, 'toggleBlocking'])
        ->add(new RequireRole($session, ['TECHNICIEN', 'ADMIN']));

};