-- 005_performance_indexes.sql
-- Add missing indexes on foreign key and date columns (issue #56)

ALTER TABLE `prescriptions`
    ADD INDEX IF NOT EXISTS `idx_prescriptions_patient_id` (`patient_id`),
    ADD INDEX IF NOT EXISTS `idx_prescriptions_doctor_id`  (`doctor_id`),
    ADD INDEX IF NOT EXISTS `idx_prescriptions_created_at` (`created_at`);

ALTER TABLE `prescription_items`
    ADD INDEX IF NOT EXISTS `idx_prescription_items_prescription_id` (`prescription_id`);

ALTER TABLE `waiting_queue`
    ADD INDEX IF NOT EXISTS `idx_waiting_queue_doctor_id`  (`doctor_id`),
    ADD INDEX IF NOT EXISTS `idx_waiting_queue_created_at` (`created_at`);

ALTER TABLE `channeling_bills`
    ADD INDEX IF NOT EXISTS `idx_channeling_bills_patient_id`  (`patient_id`),
    ADD INDEX IF NOT EXISTS `idx_channeling_bills_doctor_id`   (`doctor_id`),
    ADD INDEX IF NOT EXISTS `idx_channeling_bills_created_at`  (`created_at`);
