<?php

declare(strict_types=1);

use App\Controller\EquipmentController;
use Slim\App;

return static function (App $app, EquipmentController $controller): void {
    $app->get('/', [$controller, 'index']);
};
