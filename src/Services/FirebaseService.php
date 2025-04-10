<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Exception\MessagingException;

class FirebaseService
{
    private $messaging;

    public function __construct(string $credentialsPath)
    {
        if (!file_exists($credentialsPath)) {
            throw new \RuntimeException('Firebase kimlik bilgileri dosyası bulunamadı: ' . $credentialsPath);
        }
        
        try {
            $serviceAccountJson = json_decode(file_get_contents($credentialsPath), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Firebase kimlik bilgileri dosyası geçerli bir JSON değil');
            }
            
            $factory = (new Factory())
                ->withServiceAccount($serviceAccountJson);
            
            $this->messaging = $factory->createMessaging();
        } catch (\Exception $e) {
            throw new \RuntimeException('Firebase yapılandırması yüklenirken hata: ' . $e->getMessage());
        }
    }

    /**
     * Tek bir cihaza bildirim gönderir
     * 
     * @param string $deviceToken Hedef cihazın FCM token'ı
     * @param string $title Bildirim başlığı
     * @param string $body Bildirim içeriği
     * @param array $data Bildirime eklenecek ek veriler (isteğe bağlı)
     * @return array Gönderim sonucu
     */
    public function sendNotification(string $deviceToken, string $title, string $body, array $data = []): array
    {
        try {
            $notification = Notification::create($title, $body);
            
            $message = CloudMessage::withTarget('token', $deviceToken)
                ->withNotification($notification);
            
            if (!empty($data)) {
                $message = $message->withData($data);
            }
            
            $response = $this->messaging->send($message);
            
            return [
                'success' => true,
                'message' => 'Bildirim başarıyla gönderildi',
                'data' => json_decode(json_encode($response), true)
            ];
        } catch (MessagingException $e) {
            return [
                'success' => false,
                'message' => 'Bildirim gönderilirken hata oluştu: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Beklenmeyen bir hata oluştu: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Birden fazla cihaza aynı bildirimi gönderir
     * 
     * @param array $deviceTokens Hedef cihazların FCM token'ları
     * @param string $title Bildirim başlığı
     * @param string $body Bildirim içeriği
     * @param array $data Bildirime eklenecek ek veriler (isteğe bağlı)
     * @return array Gönderim sonucu
     */
    public function sendMulticastNotification(array $deviceTokens, string $title, string $body, array $data = []): array
    {
        try {
            $notification = Notification::create($title, $body);
            
            $message = CloudMessage::new()
                ->withNotification($notification);
            
            if (!empty($data)) {
                $message = $message->withData($data);
            }
            
            $response = $this->messaging->sendMulticast($message, $deviceTokens);
            
            return [
                'success' => true,
                'message' => 'Bildirimler başarıyla gönderildi',
                'data' => [
                    'successCount' => $response->successes()->count(),
                    'failureCount' => $response->failures()->count(),
                    'failures' => json_decode(json_encode($response->failures()->getItems()), true)
                ]
            ];
        } catch (MessagingException $e) {
            return [
                'success' => false,
                'message' => 'Bildirim gönderilirken hata oluştu: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Beklenmeyen bir hata oluştu: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Bir konuya abone olan tüm cihazlara bildirim gönderir
     * 
     * @param string $topic Konu adı
     * @param string $title Bildirim başlığı
     * @param string $body Bildirim içeriği
     * @param array $data Bildirime eklenecek ek veriler (isteğe bağlı)
     * @return array Gönderim sonucu
     */
    public function sendTopicNotification(string $topic, string $title, string $body, array $data = []): array
    {
        try {
            $notification = Notification::create($title, $body);
            
            $message = CloudMessage::withTarget('topic', $topic)
                ->withNotification($notification);
            
            if (!empty($data)) {
                $message = $message->withData($data);
            }
            
            $response = $this->messaging->send($message);
            
            return [
                'success' => true,
                'message' => 'Bildirim konuya başarıyla gönderildi',
                'data' => json_decode(json_encode($response), true)
            ];
        } catch (MessagingException $e) {
            return [
                'success' => false,
                'message' => 'Bildirim gönderilirken hata oluştu: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Beklenmeyen bir hata oluştu: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Bir cihazı bir konuya abone eder
     * 
     * @param mixed $deviceTokens Abone edilecek cihaz token(ları) - string veya array olabilir
     * @param string $topic Konu adı
     * @return array İşlem sonucu
     */
    public function subscribeToTopic($deviceTokens, string $topic): array
    {
        try {
            $tokens = is_array($deviceTokens) ? $deviceTokens : [$deviceTokens];
            
            $response = $this->messaging->subscribeToTopic($topic, $tokens);
            
            $failuresArray = [];
            foreach ($response->failures() as $failure) {
                $failuresArray[] = [
                    'index' => $failure->index(),
                    'reason' => $failure->reason(),
                    'message' => $failure->message()
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Cihaz(lar) konuya başarıyla abone edildi',
                'data' => [
                    'successCount' => (int)$response->successCount(),
                    'failureCount' => (int)$response->failureCount(),
                    'failures' => $failuresArray
                ]
            ];
        } catch (MessagingException $e) {
            return [
                'success' => false,
                'message' => 'Konuya abone olurken hata oluştu: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Beklenmeyen bir hata oluştu: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Bir cihazın bir konuya aboneliğini kaldırır
     * 
     * @param mixed $deviceTokens Aboneliği kaldırılacak cihaz token(ları) - string veya array olabilir
     * @param string $topic Konu adı
     * @return array İşlem sonucu
     */
    public function unsubscribeFromTopic($deviceTokens, string $topic): array
    {
        try {
            $tokens = is_array($deviceTokens) ? $deviceTokens : [$deviceTokens];
            
            $response = $this->messaging->unsubscribeFromTopic($topic, $tokens);
            
            $failuresArray = [];
            foreach ($response->failures() as $failure) {
                $failuresArray[] = [
                    'index' => $failure->index(),
                    'reason' => $failure->reason(),
                    'message' => $failure->message()
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Cihaz(lar)ın konuya aboneliği başarıyla kaldırıldı',
                'data' => [
                    'successCount' => (int)$response->successCount(),
                    'failureCount' => (int)$response->failureCount(),
                    'failures' => $failuresArray
                ]
            ];
        } catch (MessagingException $e) {
            return [
                'success' => false,
                'message' => 'Konu aboneliği kaldırılırken hata oluştu: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Beklenmeyen bir hata oluştu: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }
} 