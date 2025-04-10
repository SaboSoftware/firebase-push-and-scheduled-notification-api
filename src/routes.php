<?php

use App\Controllers\NotificationController;
use App\Controllers\ScheduledNotificationController;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    // API rotaları
    $app->group('/api', function (RouteCollectorProxy $group) {
        // V1 API
        $group->group('/v1', function (RouteCollectorProxy $group) {
            // Anlık Bildirimler
            $group->group('/notifications', function (RouteCollectorProxy $group) {
                // Tek bir cihaza bildirim gönder
                $group->post('/send', NotificationController::class . ':sendSingleNotification');
                
                // Birden fazla cihaza bildirim gönder
                $group->post('/send-multiple', NotificationController::class . ':sendMulticastNotification');
                
                // Konuya bildirim gönder
                $group->post('/send-topic', NotificationController::class . ':sendTopicNotification');
                
                // Konuya abone ol
                $group->post('/subscribe-topic', NotificationController::class . ':subscribeToTopic');
                
                // Konudan aboneliği kaldır
                $group->post('/unsubscribe-topic', NotificationController::class . ':unsubscribeFromTopic');
            });
            
            // Zamanlanmış Bildirimler
            $group->group('/scheduled-notifications', function (RouteCollectorProxy $group) {
                // Bildirimi zamanla
                $group->post('/schedule', ScheduledNotificationController::class . ':scheduleNotification');
                
                // Zamanlanmış bildirimi iptal et
                $group->delete('/{id}', ScheduledNotificationController::class . ':cancelScheduledNotification');
                
                // Zamanlanmış bildirimleri listele
                $group->get('', ScheduledNotificationController::class . ':listScheduledNotifications');
                
                // Bekleyen bildirimleri işle (manuel işlem veya test için)
                $group->post('/process', ScheduledNotificationController::class . ':processScheduledNotifications');
            });
        });
    });
}; 