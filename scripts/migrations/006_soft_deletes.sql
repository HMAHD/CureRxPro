-- 006_soft_deletes.sql
-- Add soft delete support to patients and prescriptions (issue #39)

ALTER TABLE `patients` ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL;

ALTER TABLE `patients` ADD INDEX `idx_patients_deleted_at` (`deleted_at`);

ALTER TABLE `prescriptions` ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL;

ALTER TABLE `prescriptions` ADD INDEX `idx_prescriptions_deleted_at` (`deleted_at`);
