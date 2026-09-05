-- 006_soft_deletes.sql
-- Add soft delete support to patients and prescriptions (issue #39)

ALTER TABLE `patients`
    ADD COLUMN IF NOT EXISTS `deleted_at` DATETIME NULL DEFAULT NULL;

ALTER TABLE `patients`
    ADD INDEX IF NOT EXISTS `idx_patients_deleted_at` (`deleted_at`);

ALTER TABLE `prescriptions`
    ADD COLUMN IF NOT EXISTS `deleted_at` DATETIME NULL DEFAULT NULL;

ALTER TABLE `prescriptions`
    ADD INDEX IF NOT EXISTS `idx_prescriptions_deleted_at` (`deleted_at`);
