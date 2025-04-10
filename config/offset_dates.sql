-- Create offset_dates table to store multiple dates per request
CREATE TABLE IF NOT EXISTS offset_dates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    offset_request_id INT NOT NULL,
    offset_date DATE NOT NULL,
    hours INT NOT NULL,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (offset_request_id) REFERENCES offset_requests(id) ON DELETE CASCADE,
    INDEX idx_offset_request_id (offset_request_id)
);

-- Add hours column to offset_requests table if not exists
ALTER TABLE offset_requests
ADD COLUMN IF NOT EXISTS hours INT DEFAULT NULL AFTER offset_date;