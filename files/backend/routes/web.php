<?php

declare(strict_types=1);

use App\Controller\EquipmentController;
use App\Controller\ReservationController;
use App\Http\Session;
use App\Middleware\RequireRole;
use Slim\App;

return static function (
    App $app,
    EquipmentController $controller,
    ReservationController $reservations,
    Session $session
): void {
    $app->get('/', [$controller, 'index']);
    
    $app->get('/equipements', [$controller, 'index']);

    $app->post('/technicien/reservations/{id:[0-9]+}/confirmer', [$reservations, 'confirm'])
        ->add(new RequireRole($session, ['TECHNICIEN', 'ADMIN']));

    $app->post('/reservations/{id:[0-9]+}/annuler', [$reservations, 'cancel'])
    ->add(new RequireRole($session, ['ADHERENT', 'TECHNICIEN', 'ADMIN']));

    $app->post('/reservations', [$reservations, 'create'])
    ->add(new RequireRole($session, ['ADHERENT', 'TECHNICIEN', 'ADMIN']));

};