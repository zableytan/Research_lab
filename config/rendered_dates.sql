-- Create rendered_dates table to store rendered dates for offset requests
CREATE TABLE IF NOT EXISTS rendered_dates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    offset_request_id INT NOT NULL,
    rendered_date DATE NOT NULL,
    hours INT NOT NULL,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (offset_request_id) REFERENCES offset_requests(id) ON DELETE CASCADE,
    INDEX idx_offset_request_id (offset_request_id)
);