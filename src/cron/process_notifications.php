#!/opt/cpanel/ea-php81/root/usr/bin/php
<?php
/**
 * Zamanlanmış Bildirimleri İşleme Script
 * 
 * Bu script crond tarafından periyodik olarak çalıştırılır ve bekleyen bildirimleri işler.
 * Hızlı ve verimli çalışması, hata durumlarını yönetmesi ve paralel çalışmalarda çakışmaları 
 * önlemesi için tasarlanmıştır.
 * 
 * Örnek crontab girişi:
 * * * * * * /opt/cpanel/ea-php81/root/usr/bin/php /home/saboproj/pengu.saboproje.com/src/cron/process_notifications.php 50 >> /home/saboproj/logs/cron.log 2>&1
 */

// PHP sürümü kontrolü
if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    die('Bu script PHP 8.1 veya üstü gerektirir. Mevcut sürüm: ' . PHP_VERSION . "\n");
}

// Başlangıç saati - performans ölçümü için
$startTime = microtime(true);

// Yapılandırma
$includePath = dirname(dirname(__DIR__));
require_once $includePath . '/vendor/autoload.php';

// Konfigürasyon dosyasını yükle
$config = require $includePath . '/config/app.php';

try {
    // PDO bağlantısı
    $pdo = new PDO(
        $config['database']['dsn'],
        $config['database']['username'],
        $config['database']['password'],
        $config['database']['options'] ?? []
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Firebase servisi
    $firebaseService = new \App\Services\FirebaseService($config['firebase']['credentials']);
    
    // Zamanlanmış bildirim servisi
    $notificationService = new \App\Services\ScheduledNotificationService(
        $pdo,
        $firebaseService,
        realpath($includePath . '/../..')
    );
    
    // Bildirimleri işle
    $result = $notificationService->processScheduledNotifications(100);
    
    // Performans ölçümünü tamamla
    $endTime = microtime(true);
    $executionTime = ($endTime - $startTime);
    
    // Sonuçları logla
    $logMessage = date('Y-m-d H:i:s') . ' - ' . json_encode($result, JSON_PRETTY_PRINT) . "\n";
    file_put_contents(
        $includePath . '/logs/notifications.log',
        $logMessage,
        FILE_APPEND
    );
    
    if (!$result['success']) {
        throw new Exception($result['message']);
    }
    
    // Sonuçları raporla
    if ($result['total'] > 0) {
        echo date('Y-m-d H:i:s') . " - İşlem tamamlandı: {$result['sent']} bildirim gönderildi, " . 
             "{$result['failed']} bildirim başarısız oldu. " .
             "(Süre: {$result['execution_time']} saniye, " . 
             "Hız: {$result['notifications_per_second']} bildirim/saniye)\n";
    } else {
        echo date('Y-m-d H:i:s') . " - İşlenecek bildirim bulunamadı. " . 
             "(Süre: " . number_format($executionTime, 4) . " saniye)\n";
    }
    
    exit(0);
} catch (Exception $e) {
    // Hataları logla
    $errorMessage = date('Y-m-d H:i:s') . ' - ERROR: ' . $e->getMessage() . "\n";
    file_put_contents(
        $includePath . '/logs/notifications_error.log',
        $errorMessage,
        FILE_APPEND
    );
    
    exit(1);
} 