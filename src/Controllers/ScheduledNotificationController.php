<?php

namespace App\Controllers;

use App\Services\ScheduledNotificationService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ScheduledNotificationController
{
    private $scheduledNotificationService;

    public function __construct(ScheduledNotificationService $scheduledNotificationService)
    {
        $this->scheduledNotificationService = $scheduledNotificationService;
    }
    
    /**
     * Bildirimi zamanla
     */
    public function scheduleNotification(Request $request, Response $response): Response
    {
        // Farklı kaynaklardan gelen verileri kontrol et
        $data = $request->getParsedBody();
        
        // Eğer getParsedBody boş dönerse, raw JSON verisi okumayı dene
        if (empty($data)) {
            $jsonData = $request->getBody()->getContents();
            $data = json_decode($jsonData, true) ?: [];
        }
        
        // Gerekli alanların kontrolü
        if (empty($data['device_token']) || empty($data['title']) || empty($data['body']) || empty($data['scheduled_time'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'device_token, title, body ve scheduled_time alanları zorunludur'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        // İsteğe bağlı ek veri
        $additionalData = isset($data['data']) && is_array($data['data']) ? $data['data'] : [];
        
        // Bildirimi zamanla
        $result = $this->scheduledNotificationService->scheduleNotification(
            $data['device_token'],
            $data['title'],
            $data['body'],
            $data['scheduled_time'],
            $additionalData
        );
        
        $statusCode = $result['success'] ? 200 : 500;
        
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
    }
    
    /**
     * Zamanlanmış bildirimi iptal et
     */
    public function cancelScheduledNotification(Request $request, Response $response, array $args): Response
    {
        $notificationId = (int) $args['id'];
        
        $result = $this->scheduledNotificationService->cancelScheduledNotification($notificationId);
        
        $statusCode = $result['success'] ? 200 : ($result['message'] === 'Bildirim bulunamadı' ? 404 : 500);
        
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
    }
    
    /**
     * Zamanlanmış bildirimleri listele
     */
    public function listScheduledNotifications(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $status = isset($params['status']) ? $params['status'] : null;
        
        $result = $this->scheduledNotificationService->listScheduledNotifications($status);
        
        $statusCode = $result['success'] ? 200 : 500;
        
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
    }
    
    /**
     * Bekleyen bildirimleri işle - Genellikle cron tarafından çağrılır
     */
    public function processScheduledNotifications(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $limit = isset($params['limit']) && is_numeric($params['limit']) ? (int)$params['limit'] : 100;
        
        $result = $this->scheduledNotificationService->processScheduledNotifications($limit);
        
        $statusCode = $result['success'] ? 200 : 500;
        
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
    }
} 