-- Create leave_dates table to store multiple dates per request
CREATE TABLE IF NOT EXISTS leave_dates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    leave_request_id INT NOT NULL,
    leave_date DATE NOT NULL,
    hours INT NOT NULL,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (leave_request_id) REFERENCES leave_requests(id) ON DELETE CASCADE,
    INDEX idx_leave_request_id (leave_request_id)
);

-- Add hours column to leave_requests table if not exists
ALTER TABLE leave_requests
ADD COLUMN IF NOT EXISTS hours INT DEFAULT NULL AFTER end_date;