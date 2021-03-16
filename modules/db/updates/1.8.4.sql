ALTER TABLE `db_model_logs`
	ADD COLUMN `user_id` INT(11) NULL AFTER `record_datetime`,
	ADD COLUMN `user_name` VARCHAR(255) NULL AFTER `user_id`;
