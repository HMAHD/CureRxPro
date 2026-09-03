-- 004_fulltext_indexes.sql - Add Full-Text & B-Tree Indexes for Fast Patient & Drug Lookup (Issue #11)
ALTER TABLE `patients` ADD INDEX `idx_patient_code` (`patient_code`);
ALTER TABLE `patients` ADD INDEX `idx_patient_phone` (`phone`);
ALTER TABLE `patients` ADD FULLTEXT INDEX `idx_patient_search` (`name`, `phone`, `patient_code`);
ALTER TABLE `medicines` ADD FULLTEXT INDEX `idx_medicine_search` (`name`, `generic_name`);
