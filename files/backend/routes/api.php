<?php

declare(strict_types=1);

use App\Controller\EquipmentController;
use App\Controller\ReservationController;
use App\Http\JsonResponder;
use App\Http\Session;
use App\Middleware\ApiWriteGuard;
use App\Middleware\RequireRole;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return static function (
    App $app,
    EquipmentController $equipment,
    ReservationController $reservations,
    Session $session
): void {
    $app->group('/api', function (RouteCollectorProxy $group) use ($equipment, $reservations, $session): void {
        $group->get('/health', function (Request $request, Response $response): Response {
            return JsonResponder::respond($response, ['status' => 'ok']);
        });

        $group->get('/equipment', [$equipment, 'apiIndex']);
        $group->get('/equipment/{id:[0-9]+}', [$equipment, 'apiShow']);

        // Toute personne connectée peut réserver ; les règles métier sont dans le service.
        $group->post('/reservations', [$reservations, 'create'])
            ->add(new RequireRole($session, ['ADHERENT', 'TECHNICIEN', 'ADMIN'], true));
    })->add(new ApiWriteGuard());
};
