# cPanel Kurulum ve Yapılandırma

Bu belge, Firebase Notification API'sini cPanel hosting'de nasıl kurup yapılandıracağınızı anlatmaktadır.

## Dosyaları Yükleme

1. Tüm dosyaları `public_html` klasörünün dışındaki bir dizine yükleyin (örneğin `firebase_backend`)
2. `public` klasörünü `public_html` dizinine bir alt alan adı olarak konumlandırabilirsiniz
   (örneğin, `public_html/api.yourdomain.com`)
3. Domains(etki alanları) kısmından yönet > new document root kısmından > sizindomain.com/public şeklinde yapmanız gerekebilir (bu ayarı kendinize göre düzenleyebilirsiniz.)

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

1. Firebase konsolundan indirdiğiniz servis hesabı anahtarınızı bu `config/firebase-credentials.json` dosyaya yapıştırın
2. `config/app.php` dosyasını düzenleyerek veritabanı ayarlarını yapılandırın:
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
 /opt/cpanel/ea-php81/root/usr/bin/php /home/sizinsunucu/sizindomain/src/cron/process_notifications.php >> /home/sizinsunucu/logs/notifications.log 2>&1
```

Bu görev, her 5 dakikada bir çalışacak ve zamanı gelen bildirimleri işleyecektir. Tabiki bu ayarları sizler kendinize göre düzenleyebilirsiniz.

Ve işlem bu kadar artık apiniz hazır.

## Güvenlik Notları

1. Firebase kimlik bilgilerini güvenli tutun
2. `public_html` dışında bir yerde saklayın
3. `.htaccess` ile API erişimini kontrol edin
4. Üretim ortamında HTTPS kullanın 
