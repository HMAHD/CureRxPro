-- 003_waiting_queue.sql - Create waiting_queue table
CREATE TABLE IF NOT EXISTS `waiting_queue` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT NOT NULL,
    `doctor_id` INT NOT NULL,
    `token_number` INT NOT NULL,
    `status` ENUM('Waiting', 'Serving', 'Completed', 'Cancelled') DEFAULT 'Waiting',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
