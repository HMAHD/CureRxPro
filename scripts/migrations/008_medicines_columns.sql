-- 008_medicines_columns.sql
-- Add new column names used by MedicineRepository; old columns kept for compatibility

ALTER TABLE `medicines`
    ADD COLUMN IF NOT EXISTS `strength`       VARCHAR(100) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `unit`           VARCHAR(50)  NOT NULL DEFAULT 'Tablet',
    ADD COLUMN IF NOT EXISTS `instruction_en` TEXT         NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `instruction_si` TEXT         NULL DEFAULT NULL;
