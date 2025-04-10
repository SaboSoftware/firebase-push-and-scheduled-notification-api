<?php

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// PHP hata ayıklama ayarları
$config = require __DIR__ . '/../config/app.php';
ini_set('display_errors', $config['displayErrors']);
error_reporting(E_ALL);

// Bağımlılık Enjeksiyon konteynerini oluştur
$containerBuilder = new ContainerBuilder();
$dependencies = require __DIR__ . '/../src/dependencies.php';
$dependencies($containerBuilder);
$container = $containerBuilder->build();

// Slim uygulama örneğini oluştur
AppFactory::setContainer($container);
$app = AppFactory::create();

// JSON gövde ayrıştırma middleware'ini ekle
$app->addBodyParsingMiddleware();

// CORS ayarları
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});

// OPTIONS istekleri için yanıt
$app->options('/{routes:.+}', function ($request, $response) {
    return $response;
});

// Slim JSON hata işleyicisini ayarla
$app->addErrorMiddleware($config['displayErrors'], true, true);

// Rotaları ekle
$routes = require __DIR__ . '/../src/routes.php';
$routes($app);

// Uygulamayı çalıştır
$app->run(); 