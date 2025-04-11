-- Zamanlanmış bildirimler tablosu
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