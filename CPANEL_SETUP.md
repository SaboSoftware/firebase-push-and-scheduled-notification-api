# cPanel Kurulum ve Yapılandırma

Bu belge, Firebase Notification API'sini cPanel hosting'de nasıl kurup yapılandıracağınızı anlatmaktadır.

## Dosyaları Yükleme

1. Tüm dosyaları `public_html` klasörünün dışındaki bir dizine yükleyin (örneğin `firebase_backend`)
2. `public` klasörünü `public_html` dizinine bir alt alan adı olarak konumlandırabilirsiniz
   (örneğin, `public_html/api.yourdomain.com`)

## PHP Sürümünü Ayarlama

cPanel'de PHP 8.1 veya daha yüksek sürüm kullanmanız gerekmektedir:

1. cPanel > Software > MultiPHP Manager menüsüne gidin
2. Sitenizi seçin ve PHP sürümünü 8.1 veya daha yüksek bir sürüme ayarlayın

## Veritabanı Oluşturma

1. cPanel > Databases > MySQL Databases menüsüne gidin
2. Yeni bir veritabanı oluşturun
3. Yeni bir veritabanı kullanıcısı oluşturun
4. Kullanıcıyı veritabanına atayın, tüm yetkileri verin
5. PhpMyAdmin'e girerek aşağıdaki SQL dosyasını içe aktarın: `database/schema.sql`

## Yapılandırma

1. `config/firebase-credentials-example.json` dosyasını `config/firebase-credentials.json` olarak kopyalayın
2. Firebase konsolundan indirdiğiniz servis hesabı anahtarınızı bu dosyaya yapıştırın
3. `config/app.php` dosyasını düzenleyerek veritabanı ayarlarını yapılandırın:

```php
'database' => [
    'dsn' => 'mysql:host=localhost;dbname=YOUR_DB_NAME;charset=utf8mb4',
    'username' => 'YOUR_DB_USER',
    'password' => 'YOUR_DB_PASSWORD',
    'options' => [
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false
    ]
]
```

## Composer Paketlerini Kurma

cPanel'de SSH erişiminiz yoksa, Composer paketlerini lokal bilgisayarınızda kurup, tüm `vendor` klasörünü FTP ile sunucuya yükleyebilirsiniz:

1. Lokal bilgisayarınızda projeyi klonlayın
2. `composer install --no-dev` komutunu çalıştırın
3. Oluşturulan `vendor` klasörünün tamamını FTP ile sunucuya yükleyin

SSH erişiminiz varsa:

1. SSH ile sunucuya bağlanın
2. Proje klasörüne gidin
3. `composer install --no-dev` komutunu çalıştırın

## Cron İşlemleri İçin Dizin Yapısı

Uygulama, zamanlanmış bildirimler için cron kullanır. İlgili dizinlerin erişilebilir olması gerekir:

1. Projenin ana klasöründe bir `var` dizini oluşturun: `mkdir var`
2. Bu dizine yazma izni verin: `chmod 755 var`

## Cron Kurulumu

### 1. Zamanlı Bildirimleri İşlemek İçin Ana Cron Görevi

cPanel > Advanced > Cron Jobs menüsünden, aşağıdaki cron görevi ekleyin:

```
*/5 * * * * cd /home/username/firebase_backend && php bin/console notifications:process-pending > /dev/null 2>&1
```

Bu görev, her 5 dakikada bir çalışacak ve zamanı gelen bildirimleri işleyecektir.

### 2. Otomatik Cron Yönetimi

Uygulamanın otomatik olarak cron görevleri oluşturabilmesi için iki yöntem vardır:

#### A. Crontab Kullanımı (SSH erişimi varsa)

1. SSH erişiminiz olduğundan emin olun
2. Uygulamayı çalıştıran kullanıcıya crontab'ı değiştirme izni verin
3. Kod içinde özelleştirme yapmaya gerek yok; mevcut kod çalışacaktır

#### B. cPanel Cron API Kullanımı (SSH erişimi yoksa)

cPanel API kullanarak cron görevi oluşturmak için aşağıdaki kodu `src/Services/ScheduledNotificationService.php` dosyasında `createCronJob` metoduna ekleyin:

```php
// cPanel API kullanarak cron görevi oluştur
$cpanelUser = 'your_cpanel_username';
$cpanelApiToken = 'your_cpanel_api_token';
$domain = 'yourdomain.com';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://{$domain}:2083/execute/Cron/add_line");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: cpanel ' . $cpanelUser . ':' . $cpanelApiToken
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'command' => $command,
    'minute' => $minute,
    'hour' => $hour,
    'day' => $day,
    'month' => $month,
    'weekday' => '*'
]));

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
return !empty($result['data']);
```

Benzer şekilde, `removeCronJob` metodunu da cPanel API kullanacak şekilde değiştirin.

## API Kullanımı

Firebase bildirim API'si artık çalışır durumda! API'yi test etmek için:

### Zamanlanmış Bildirim Oluşturma

```
POST /api/v1/scheduled-notifications/schedule
```

İstek gövdesi:
```json
{
  "device_token": "cihaz_fcm_token_buraya",
  "title": "Bildirim Başlığı",
  "body": "Bildirim İçeriği",
  "scheduled_time": "2023-10-15 14:30:00",
  "data": {
    "key1": "value1",
    "key2": "value2"
  }
}
```

### Zamanlanmış Bildirimleri Listeleme

```
GET /api/v1/scheduled-notifications
```

İsteğe bağlı olarak, duruma göre filtreleme yapabilirsiniz:

```
GET /api/v1/scheduled-notifications?status=pending
```

### Zamanlanmış Bildirimi İptal Etme

```
DELETE /api/v1/scheduled-notifications/123
```

(123, bildirim ID'sidir)

## Güvenlik Notları

1. Firebase kimlik bilgilerini güvenli tutun
2. `public_html` dışında bir yerde saklayın
3. `.htaccess` ile API erişimini kontrol edin
4. Üretim ortamında HTTPS kullanın 