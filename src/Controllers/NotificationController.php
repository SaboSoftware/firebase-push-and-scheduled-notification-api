<?php

namespace App\Controllers;

use App\Services\FirebaseService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class NotificationController
{
    private $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Tekli bildirim gönderme endpoint'i
     */
    public function sendSingleNotification(Request $request, Response $response): Response
    {
        // Farklı kaynaklardan gelen verileri kontrol et
        $data = $request->getParsedBody();
        
        // Eğer getParsedBody boş dönerse, raw JSON verisi okumayı dene
        if (empty($data)) {
            $jsonData = $request->getBody()->getContents();
            $data = json_decode($jsonData, true) ?: [];
            // JSON dönüştürme hatası olup olmadığını kontrol et
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('JSON decode error: ' . json_last_error_msg());
            }
        }
        
        // Debug için log tutabiliriz
        error_log('Content-Type: ' . $request->getHeaderLine('Content-Type'));
        error_log('Request data: ' . print_r($data, true));
        
        // Gerekli alanların kontrolü
        if (empty($data['device_token']) || empty($data['title']) || empty($data['body'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'device_token, title ve body alanları zorunludur',
                'received_data' => $data, // Bu satır hata ayıklama sırasında hangi verilerin geldiğini görmek için
                'content_type' => $request->getHeaderLine('Content-Type'),
                'method' => $request->getMethod()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        // İsteğe bağlı ek veri
        $additionalData = isset($data['data']) && is_array($data['data']) ? $data['data'] : [];
        
        // Bildirimi gönder
        $result = $this->firebaseService->sendNotification(
            $data['device_token'],
            $data['title'],
            $data['body'],
            $additionalData
        );
        
        $statusCode = $result['success'] ? 200 : 500;
        
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
    }
    
    /**
     * Çoklu bildirim gönderme endpoint'i
     */
    public function sendMulticastNotification(Request $request, Response $response): Response
    {
        // Farklı kaynaklardan gelen verileri kontrol et
        $data = $request->getParsedBody();
        
        // Eğer getParsedBody boş dönerse, raw JSON verisi okumayı dene
        if (empty($data)) {
            $jsonData = $request->getBody()->getContents();
            $data = json_decode($jsonData, true) ?: [];
        }
        
        // Gerekli alanların kontrolü
        if (empty($data['device_tokens']) || empty($data['title']) || empty($data['body'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'device_tokens, title ve body alanları zorunludur'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        // Device tokens'ın bir dizi olduğundan emin ol
        if (!is_array($data['device_tokens'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'device_tokens bir dizi olmalıdır'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        // İsteğe bağlı ek veri
        $additionalData = isset($data['data']) && is_array($data['data']) ? $data['data'] : [];
        
        // Bildirimi gönder
        $result = $this->firebaseService->sendMulticastNotification(
            $data['device_tokens'],
            $data['title'],
            $data['body'],
            $additionalData
        );
        
        $statusCode = $result['success'] ? 200 : 500;
        
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
    }
    
    /**
     * Konu tabanlı bildirim gönderme endpoint'i
     */
    public function sendTopicNotification(Request $request, Response $response): Response
    {
        // Farklı kaynaklardan gelen verileri kontrol et
        $data = $request->getParsedBody();
        
        // Eğer getParsedBody boş dönerse, raw JSON verisi okumayı dene
        if (empty($data)) {
            $jsonData = $request->getBody()->getContents();
            $data = json_decode($jsonData, true) ?: [];
        }
        
        // Gerekli alanların kontrolü
        if (empty($data['topic']) || empty($data['title']) || empty($data['body'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'topic, title ve body alanları zorunludur'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        // İsteğe bağlı ek veri
        $additionalData = isset($data['data']) && is_array($data['data']) ? $data['data'] : [];
        
        // Bildirimi gönder
        $result = $this->firebaseService->sendTopicNotification(
            $data['topic'],
            $data['title'],
            $data['body'],
            $additionalData
        );
        
        $statusCode = $result['success'] ? 200 : 500;
        
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
    }
    
    /**
     * Konuya abone olma endpoint'i
     */
    public function subscribeToTopic(Request $request, Response $response): Response
    {
        // Farklı kaynaklardan gelen verileri kontrol et
        $data = $request->getParsedBody();
        
        // Eğer getParsedBody boş dönerse, raw JSON verisi okumayı dene
        if (empty($data)) {
            $jsonData = $request->getBody()->getContents();
            $data = json_decode($jsonData, true) ?: [];
        }
        
        // Gerekli alanların kontrolü
        if (empty($data['device_tokens']) || empty($data['topic'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'device_tokens ve topic alanları zorunludur'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        // Device tokens'ın bir dizi veya string olduğundan emin ol
        if (!is_array($data['device_tokens']) && !is_string($data['device_tokens'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'device_tokens bir dizi veya string olmalıdır'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        // Abone et
        $result = $this->firebaseService->subscribeToTopic(
            $data['device_tokens'],
            $data['topic']
        );
        
        $statusCode = $result['success'] ? 200 : 500;
        
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
    }
    
    /**
     * Konudan abonelik silme endpoint'i
     */
    public function unsubscribeFromTopic(Request $request, Response $response): Response
    {
        // Farklı kaynaklardan gelen verileri kontrol et
        $data = $request->getParsedBody();
        
        // Eğer getParsedBody boş dönerse, raw JSON verisi okumayı dene
        if (empty($data)) {
            $jsonData = $request->getBody()->getContents();
            $data = json_decode($jsonData, true) ?: [];
        }
        
        // Gerekli alanların kontrolü
        if (empty($data['device_tokens']) || empty($data['topic'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'device_tokens ve topic alanları zorunludur'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        // Device tokens'ın bir dizi veya string olduğundan emin ol
        if (!is_array($data['device_tokens']) && !is_string($data['device_tokens'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'device_tokens bir dizi veya string olmalıdır'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        // Aboneliği kaldır
        $result = $this->firebaseService->unsubscribeFromTopic(
            $data['device_tokens'],
            $data['topic']
        );
        
        $statusCode = $result['success'] ? 200 : 500;
        
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
    }
} 