<?php

use App\Controllers\NotificationController;
use App\Controllers\ScheduledNotificationController;
use App\Services\FirebaseService;
use App\Services\ScheduledNotificationService;
use DI\ContainerBuilder;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        // Konfigürasyon
        'config' => function () {
            return require __DIR__ . '/../config/app.php';
        },
        
        // Veritabanı
        PDO::class => function ($container) {
            $config = $container->get('config');
            $dsn = $config['database']['dsn'];
            $username = $config['database']['username'];
            $password = $config['database']['password'];
            $options = $config['database']['options'] ?? [];
            
            $pdo = new PDO($dsn, $username, $password, $options);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            return $pdo;
        },
        
        // Firebase servisi
        FirebaseService::class => function ($container) {
            $config = $container->get('config');
            return new FirebaseService($config['firebase']['credentials']);
        },
        
        // Zamanlanmış bildirim servisi
        ScheduledNotificationService::class => function ($container) {
            $pdo = $container->get(PDO::class);
            $firebaseService = $container->get(FirebaseService::class);
            $basePath = realpath(__DIR__ . '/..');
            
            return new ScheduledNotificationService($pdo, $firebaseService, $basePath);
        },
        
        // Kontrolörler
        NotificationController::class => function ($container) {
            return new NotificationController(
                $container->get(FirebaseService::class)
            );
        },
        
        ScheduledNotificationController::class => function ($container) {
            return new ScheduledNotificationController(
                $container->get(ScheduledNotificationService::class)
            );
        }
    ]);
}; 