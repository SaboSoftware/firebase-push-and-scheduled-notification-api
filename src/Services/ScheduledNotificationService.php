<?php

namespace App\Services;

use PDO;
use DateTimeImmutable;
use Exception;
use DateTimeZone;

class ScheduledNotificationService
{
    private $pdo;
    private $firebaseService;
    private $basePath;
    private $timezone;

    public function __construct(PDO $pdo, FirebaseService $firebaseService, string $basePath)
    {
        $this->pdo = $pdo;
        $this->firebaseService = $firebaseService;
        $this->basePath = rtrim($basePath, '/');
        
        // Türkiye zaman dilimini ayarla
        $this->timezone = new DateTimeZone('Europe/Istanbul');
        date_default_timezone_set('Europe/Istanbul');
    }

    /**
     * Bildirim zamanlar
     * 
     * @param string $deviceToken Cihaz token'ı
     * @param string $title Bildirim başlığı
     * @param string $body Bildirim mesajı
     * @param string $scheduledDateTime Y-m-d H:i:s formatında tarih ve saat (örn: 2023-10-15 14:30:00)
     * @param array $data İsteğe bağlı ekstra veri
     * @return array İşlem sonucu
     */
    public function scheduleNotification(string $deviceToken, string $title, string $body, string $scheduledDateTime, array $data = []): array
    {
        try {
            // Tarih formatını kontrol et (Türkiye saati ile)
            $dateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $scheduledDateTime, $this->timezone);
            if (!$dateTime) {
                return [
                    'success' => false,
                    'message' => 'Geçersiz tarih formatı. Beklenen format: Y-m-d H:i:s (örn: 2023-10-15 14:30:00)'
                ];
            }

            // Geçmiş tarih kontrolü (Türkiye saati ile)
            $now = new DateTimeImmutable('now', $this->timezone);
            if ($dateTime < $now) {
                return [
                    'success' => false,
                    'message' => 'Geçmiş tarihler için bildirim zamanlanamaz'
                ];
            }

            // Zamanlanmış bildirimi veritabanına kaydet
            $stmt = $this->pdo->prepare(
                "INSERT INTO scheduled_notifications 
                (device_token, title, body, additional_data, scheduled_time, status, created_at) 
                VALUES (?, ?, ?, ?, ?, 'pending', NOW())"
            );
            
            $stmt->execute([
                $deviceToken,
                $title,
                $body,
                json_encode($data),
                $dateTime->format('Y-m-d H:i:s')
            ]);
            
            $notificationId = $this->pdo->lastInsertId();
            
            // Cron görevi oluştur
            $this->createCronJob($notificationId, $dateTime->format('Y-m-d H:i:s'));
            
            return [
                'success' => true,
                'message' => 'Bildirim başarıyla zamanlandı',
                'data' => [
                    'notification_id' => $notificationId,
                    'scheduled_time' => $dateTime->format('Y-m-d H:i:s')
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Bildirim zamanlanırken hata oluştu: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Zamanlanmış bir bildirimi iptal eder
     * 
     * @param int $notificationId Bildirim ID'si
     * @return array İşlem sonucu
     */
    public function cancelScheduledNotification(int $notificationId): array
    {
        try {
            // Bildirim durumunu kontrol et
            $stmt = $this->pdo->prepare("SELECT * FROM scheduled_notifications WHERE id = ?");
            $stmt->execute([$notificationId]);
            $notification = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$notification) {
                return [
                    'success' => false,
                    'message' => 'Bildirim bulunamadı'
                ];
            }
            
            if ($notification['status'] !== 'pending') {
                return [
                    'success' => false,
                    'message' => 'Sadece bekleyen bildirimler iptal edilebilir'
                ];
            }
            
            // Cron görevini kaldır
            $this->removeCronJob($notificationId);
            
            // Bildirim durumunu güncelle
            $stmt = $this->pdo->prepare("UPDATE scheduled_notifications SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$notificationId]);
            
            return [
                'success' => true,
                'message' => 'Bildirim başarıyla iptal edildi',
                'data' => [
                    'notification_id' => $notificationId
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Bildirim iptal edilirken hata oluştu: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Zamanlanmış bildirimleri listeler
     * 
     * @param string $status Bildirim durumu (pending, sent, cancelled, failed) - opsiyonel
     * @return array Bildirimlerin listesi
     */
    public function listScheduledNotifications(?string $status = null): array
    {
        try {
            $query = "SELECT * FROM scheduled_notifications";
            $params = [];
            
            if ($status) {
                $query .= " WHERE status = ?";
                $params[] = $status;
            }
            
            $query .= " ORDER BY scheduled_time DESC";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Ek verileri JSON'dan döndür
            foreach ($notifications as &$notification) {
                if (!empty($notification['additional_data'])) {
                    $notification['additional_data'] = json_decode($notification['additional_data'], true);
                }
            }
            
            return [
                'success' => true,
                'message' => 'Bildirimler başarıyla listelendi',
                'data' => [
                    'notifications' => $notifications,
                    'count' => count($notifications)
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Bildirimler listelenirken hata oluştu: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Bekleyen bildirimleri işler - Cron tarafından çağrılır
     * 
     * @param int $limit İşlenecek maksimum bildirim sayısı (opsiyonel)
     * @return array İşlem sonucu
     */
    public function processScheduledNotifications(int $limit = 100): array
    {
        try {
            // Başlangıç zamanı
            $startTime = microtime(true);
            
            // Şu anki zamanı al (Türkiye saati ile)
            $now = new DateTimeImmutable('now', $this->timezone);
            
            // Zamanı gelmiş ve bekleyen bildirimleri seç
            $stmt = $this->pdo->prepare(
                "SELECT * FROM scheduled_notifications 
                WHERE status = 'pending' 
                AND scheduled_time <= ? 
                ORDER BY scheduled_time ASC 
                LIMIT ?"
            );
            
            $stmt->execute([
                $now->format('Y-m-d H:i:s'),
                $limit
            ]);
            
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($notifications)) {
                return [
                    'success' => true,
                    'message' => 'İşlenecek zamanlanmış bildirim bulunamadı',
                    'data' => [
                        'processed' => 0,
                        'sent' => 0,
                        'failed' => 0,
                        'execution_time' => round(microtime(true) - $startTime, 2),
                        'current_time' => $now->format('Y-m-d H:i:s')
                    ]
                ];
            }
            
            $sent = 0;
            $failed = 0;
            $results = [];
            
            foreach ($notifications as $notification) {
                try {
                    // Bildirimi gönder
                    $result = $this->firebaseService->sendNotification(
                        $notification['device_token'],
                        $notification['title'],
                        $notification['body'],
                        json_decode($notification['additional_data'] ?? '{}', true)
                    );
                    
                    // Bildirim durumunu güncelle
                    $updateStmt = $this->pdo->prepare(
                        "UPDATE scheduled_notifications 
                        SET status = ?, 
                            sent_at = NOW(),
                            updated_at = NOW(),
                            result = ? 
                        WHERE id = ?"
                    );
                    
                    if ($result['success']) {
                        $sent++;
                        $status = 'sent';
                    } else {
                        $failed++;
                        $status = 'failed';
                    }
                    
                    $updateStmt->execute([
                        $status,
                        json_encode($result),
                        $notification['id']
                    ]);
                    
                    $results[] = [
                        'notification_id' => $notification['id'],
                        'status' => $status,
                        'result' => $result
                    ];
                    
                } catch (Exception $e) {
                    $failed++;
                    $results[] = [
                        'notification_id' => $notification['id'],
                        'status' => 'failed',
                        'error' => $e->getMessage()
                    ];
                    
                    // Hata durumunda bildirimi güncelle
                    $updateStmt = $this->pdo->prepare(
                        "UPDATE scheduled_notifications 
                        SET status = 'failed', 
                            updated_at = NOW(),
                            result = ? 
                        WHERE id = ?"
                    );
                    
                    $updateStmt->execute([
                        json_encode(['error' => $e->getMessage()]),
                        $notification['id']
                    ]);
                }
            }
            
            return [
                'success' => true,
                'message' => 'Zamanlanmış bildirimler işlendi',
                'data' => [
                    'processed' => count($notifications),
                    'sent' => $sent,
                    'failed' => $failed,
                    'results' => $results,
                    'execution_time' => round(microtime(true) - $startTime, 2)
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Bildirimler işlenirken hata oluştu: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Cron görevi oluşturur
     * 
     * @param int $notificationId Bildirim ID'si
     * @param string $scheduledDateTime Planlanan tarih ve saat
     * @return bool Başarılı mı
     */
    private function createCronJob(int $notificationId, string $scheduledDateTime): bool
    {
        // Zaten veritabanında kaydedildiği için başarılı sayılır
        return true;
    }
    
    /**
     * Cron görevini kaldırır
     * 
     * @param int $notificationId Bildirim ID'si
     * @return bool Başarılı mı
     */
    private function removeCronJob(int $notificationId): bool
    {
        // Status zaten veritabanında güncelleniyor, ek işleme gerek yok
        return true;
    }
} 