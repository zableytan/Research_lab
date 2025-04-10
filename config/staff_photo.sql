-- Add photo column to users table
ALTER TABLE users
ADD COLUMN IF NOT EXISTS photo_path VARCHAR(255) DEFAULT 'assets/images/default-avatar.svg' AFTER email;

-- Create directory for staff photos if not exists
CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert upload directory path
INSERT INTO system_settings (setting_key, setting_value)
VALUES ('staff_photos_dir', 'uploads/staff_photos/')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);