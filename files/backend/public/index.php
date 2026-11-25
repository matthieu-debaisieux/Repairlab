<?php

declare(strict_types=1);

use App\Controller\AuthController;
use App\Controller\EquipmentController;
use App\Controller\ReservationController;
use App\Database\DatabaseFactory;
use App\Http\Session;
use App\Http\ViewRenderer;
use App\Repository\CreneauPrioritaireRepository;
use App\Repository\EquipmentRepository;
use App\Repository\ReservationRepository;
use App\Repository\UserRepository;
use App\Service\AuthService;
use App\Service\EquipmentService;
use App\Service\ReservationService;
use App\Service\TechnicienDashboardService;
use Dotenv\Dotenv;
use Slim\Factory\AppFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);

Dotenv::createImmutable($root)->safeLoad();

$settings = require $root . '/config/settings.php';

$app = AppFactory::create();

$database = DatabaseFactory::create($settings['database']);
$session = new Session();
$renderer = new ViewRenderer($root . '/templates');

$equipmentController = new EquipmentController(
    new EquipmentService(new EquipmentRepository($database)),
    $renderer
);
$authController = new AuthController(
    new AuthService(new UserRepository($database)),
    $session,
    $renderer,
    new TechnicienDashboardService($database)
);
$reservationController = new ReservationController(
    new ReservationService(
        new ReservationRepository($database),
        new CreneauPrioritaireRepository($database)
    ),
    $session
);

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(
    $settings['app']['debug'],
    true,
    true
);

(require $root . '/routes/web.php')($app, $equipmentController);
(require $root . '/routes/api.php')($app, $equipmentController, $reservationController, $session);
(require $root . '/routes/auth.php')($app, $authController, $session);

$app->run();
