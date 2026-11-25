<?php

declare(strict_types=1);

/**
 * Connexion de test : même configuration que l'application (.env), aucun mot de passe en dur.
 * Attention : ces tests s'exécutent sur la base du .env et y créent puis suppriment leurs propres lignes.
 * Utiliser de préférence une base de test dédiée (DB_NAME dans un .env de test).
 */
$root = dirname(__DIR__);

require_once $root . '/vendor/autoload.php';

Dotenv\Dotenv::createImmutable($root)->safeLoad();

$settings = require $root . '/config/settings.php';

if (($settings['app']['env'] ?? 'prod') === 'prod') {
    fwrite(STDERR, "Refus : les tests ne s'exécutent pas avec APP_ENV=prod.\n");
    exit(1);
}

return App\Database\DatabaseFactory::create($settings['database']);
