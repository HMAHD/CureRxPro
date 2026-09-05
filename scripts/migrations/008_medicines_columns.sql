-- 008_medicines_columns.sql
-- Add new column names used by MedicineRepository; old columns kept for compatibility

ALTER TABLE `medicines` ADD COLUMN `strength`       VARCHAR(100) NULL DEFAULT NULL;
ALTER TABLE `medicines` ADD COLUMN `unit`           VARCHAR(50)  NOT NULL DEFAULT 'Tablet';
ALTER TABLE `medicines` ADD COLUMN `instruction_en` TEXT         NULL DEFAULT NULL;
ALTER TABLE `medicines` ADD COLUMN `instruction_si` TEXT         NULL DEFAULT NULL;
