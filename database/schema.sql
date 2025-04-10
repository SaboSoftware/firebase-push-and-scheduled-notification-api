-- Zamanlanmış bildirimler tablosu
CREATE TABLE scheduled_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_token VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    additional_data TEXT,
    scheduled_time DATETIME NOT NULL,
    status ENUM('pending', 'sent', 'cancelled', 'failed') NOT NULL DEFAULT 'pending',
    response_data TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    INDEX (status),
    INDEX (scheduled_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 