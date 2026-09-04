-- 007_audit_log.sql
-- Prescription and patient mutation audit trail (issue #58)

CREATE TABLE IF NOT EXISTS `audit_log` (
    `id`           BIGINT       NOT NULL AUTO_INCREMENT,
    `action`       ENUM('create','update','delete') NOT NULL,
    `table_name`   VARCHAR(64)  NOT NULL,
    `record_id`    INT          NOT NULL,
    `old_values`   JSON         NULL,
    `new_values`   JSON         NULL,
    `workstation`  VARCHAR(100) NULL,
    `performed_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_audit_table_record` (`table_name`, `record_id`),
    INDEX `idx_audit_performed_at` (`performed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
