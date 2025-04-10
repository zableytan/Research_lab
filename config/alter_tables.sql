-- Add hours column to leave_requests table
ALTER TABLE leave_requests
ADD COLUMN IF NOT EXISTS hours INT DEFAULT NULL AFTER end_date;

-- Create leave_dates table
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

-- Add hours column to offset_requests table
ALTER TABLE offset_requests
ADD COLUMN IF NOT EXISTS hours INT DEFAULT NULL AFTER offset_date;

-- Create offset_dates table
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

-- Create rendered_dates table
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