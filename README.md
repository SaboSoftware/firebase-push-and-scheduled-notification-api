# Firebase Anlık Push ve Zamanlanmış Bildirim Backend API Dökümantasyonu

## Genel Bakış
Firebase Backend, Firebase Cloud Messaging (FCM) kullanarak mobil uygulamalara anlık ve zamanlanmış bildirim gönderme işlemlerini yöneten bir REST API servisidir. Slim Framework 4.0 üzerine inşa edilmiş olup, PHP 8.1 ile çalışmaktadır.

## Özellikler
- Anlık bildirim gönderme
- Zamanlanmış bildirim oluşturma
- Zamanlanmış bildirimleri iptal etme
- Bildirim durumlarını listeleme
- Tek cihaza veya çoklu cihaza bildirim gönderme
- Topic (konu) bazlı bildirim gönderme
- Topic aboneliği yönetimi

## Teknik Gereksinimler
- PHP 8.1 veya üzeri
- MySQL 5.7 veya üzeri
- Firebase Admin SDK
- Composer

## Kurulum

## 1. Config Dosyasını Düzenle
config-set olan klasörün adını sadece 'config' yap

### 1. Composer Bağımlılıklarını Yükleme
```bash
composer install
```

### 2. Veritabanı Kurulumu
Aşağıdaki SQL sorgusunu çalıştırarak gerekli tabloyu oluşturun:

```sql
CREATE TABLE scheduled_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_token VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    additional_data TEXT NULL,
    scheduled_time TIMESTAMP NOT NULL,
    status ENUM('pending', 'processing', 'sent', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
    response_data TEXT NULL,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### 3. Firebase Yapılandırması
1. Firebase Console'dan servis hesabı anahtarını indirin
2. `config/app.php` dosyasında Firebase yapılandırmasını ayarlayın:

```php
return [
    'firebase' => [
        'credentials' => '/path/to/firebase-credentials.json'
    ],
    'database' => [
        'dsn' => 'mysql:host=localhost;dbname=your_db_name;charset=utf8mb4',
        'username' => 'your_username',
        'password' => 'your_password',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    ]
];
```

### 4. Cron Job Ayarları
cPanel üzerinden aşağıdaki cron job'u ekleyin (her 5 dakikada bir çalışacak şekilde) isterseniz kendi istediğiniz zaman diliminde de çalıştırabilirsiniz ihtiyacınıza göre:

```bash
*/5 * * * * /opt/cpanel/ea-php81/root/usr/bin/php /home/sizinsunucu/sizindomain/src/cron/process_notifications.php >> /home/sizinsunucu/logs/notifications.log 2>&1
```

NOT: Bu cron linki benim sunucuma göredir siz kendi sunucunuza göre ayarlayabilirsiniz.

## API Endpoint'leri

### Anlık Bildirimler

#### 1. Tek Cihaza Bildirim Gönderme
```http
POST /api/v1/notifications/send
```

**İstek Gövdesi:**
```json
{
    "device_token": "firebase_device_token",
    "title": "Bildirim Başlığı",
    "body": "Bildirim Mesajı",
    "data": {
        "key1": "value1",
        "key2": "value2"
    }
}
```

#### 2. Çoklu Cihaza Bildirim Gönderme
```http
POST /api/v1/notifications/send-multiple
```

**İstek Gövdesi:**
```json
{
    "device_tokens": ["token1", "token2", "token3"],
    "title": "Bildirim Başlığı",
    "body": "Bildirim Mesajı",
    "data": {
        "key1": "value1"
    }
}
```

#### 3. Topic'e Bildirim Gönderme
```http
POST /api/v1/notifications/send-topic
```

**İstek Gövdesi:**
```json
{
    "topic": "news",
    "title": "Bildirim Başlığı",
    "body": "Bildirim Mesajı",
    "data": {
        "key1": "value1"
    }
}
```

### Topic Yönetimi

#### 1. Topic'e Abone Olma
```http
POST /api/v1/notifications/subscribe-topic
```

**İstek Gövdesi:**
```json
{
    "device_token": "firebase_device_token",
    "topic": "news"
}
```

#### 2. Topic Aboneliğini İptal Etme
```http
POST /api/v1/notifications/unsubscribe-topic
```

**İstek Gövdesi:**
```json
{
    "device_token": "firebase_device_token",
    "topic": "news"
}
```

### Zamanlanmış Bildirimler

#### 1. Bildirim Zamanlama
```http
POST /api/v1/scheduled-notifications/schedule
```

**İstek Gövdesi:**
```json
{
    "device_token": "firebase_device_token",
    "title": "Bildirim Başlığı",
    "body": "Bildirim Mesajı",
    "scheduled_time": "2024-04-10 15:30:00",
    "data": {
        "key1": "value1"
    }
}
```

**Önemli Notlar:**
- `scheduled_time` Türkiye saati (UTC+3) ile gönderilmelidir
- Format: "Y-m-d H:i:s" (örn: "2024-04-10 15:30:00")
- Geçmiş tarihler kabul edilmez
- En yakın yarım saati seçmeniz önerilir (örn: 15:00 veya 15:30)

#### 2. Zamanlanmış Bildirimi İptal Etme
```http
DELETE /api/v1/scheduled-notifications/{notification_id}
```

#### 3. Zamanlanmış Bildirimleri Listeleme
```http
GET /api/v1/scheduled-notifications
```

**Sorgu Parametreleri:**
- `status`: Bildirim durumu filtresi (pending, sent, cancelled, failed)

### Yanıt Formatları

#### Başarılı Yanıt
```json
{
    "success": true,
    "message": "İşlem başarılı mesajı",
    "data": {
        // İşleme özgü veriler
    }
}
```

#### Hata Yanıtı
```json
{
    "success": false,
    "message": "Hata mesajı",
    "error": "Detaylı hata açıklaması"
}
```

## Bildirim Durumları
- `pending`: Bekleyen bildirim
- `processing`: İşlenmekte olan bildirim
- `sent`: Başarıyla gönderilmiş bildirim
- `failed`: Gönderim başarısız olmuş bildirim
- `cancelled`: İptal edilmiş bildirim

## Hata Kodları ve Anlamları
- `400`: Geçersiz istek formatı
- `404`: Bildirim bulunamadı
- `422`: Geçersiz veri (örn: geçersiz tarih formatı)
- `500`: Sunucu hatası

## Güvenlik
- Tüm istekler HTTPS üzerinden yapılmalıdır
- API anahtarı veya token gerektiren endpoint'ler için Authorization header'ı kullanılmalıdır
- Firebase kimlik bilgileri güvenli bir şekilde saklanmalıdır

## Mobil Uygulama Entegrasyonu

### Android
1. Firebase SDK'yı projenize ekleyin
2. Device token'ı alın:
```kotlin
FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
    if (task.isSuccessful) {
        val token = task.result
        // Bu token'ı backend'e gönderin
    }
}
```

### iOS
1. Firebase SDK'yı projenize ekleyin
2. Device token'ı alın:
```swift
Messaging.messaging().token { token, error in
    if let token = token {
        // Bu token'ı backend'e gönderin
    }
}
```

## Logging ve Hata Ayıklama
Log dosyaları şu konumlarda tutulur:
- `/home/saboproj/logs/notifications.log`: Bildirim işlem logları
- `/home/saboproj/logs/notifications_error.log`: Hata logları
- `/home/saboproj/logs/cron.log`: Cron job logları

## Öneriler ve Best Practices
1. Zamanlanmış bildirimler için en yakın yarım saati seçin (15:00, 15:30 gibi)
2. Çok sayıda bildirim gönderirken çoklu gönderim endpoint'ini kullanın
3. Topic'leri anlamlı kategorilere göre oluşturun
4. Bildirim içeriklerini kullanıcı dostu ve kısa tutun
5. Hata durumlarını düzenli olarak kontrol edin

## Sürüm Geçmişi
- v1.0.0: İlk sürüm
  - Anlık bildirim desteği
  - Zamanlanmış bildirim desteği
  - Topic yönetimi 
