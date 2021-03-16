CREATE TABLE `db_model_logs` (
	 `id` int(11) NOT NULL AUTO_INCREMENT,
	 `master_object_class` varchar(255) DEFAULT NULL,
	 `master_object_id` int(11) DEFAULT NULL,
	 `type` char(20) DEFAULT NULL,
	 `param_data` text,
	 `record_datetime` datetime DEFAULT NULL,
	 PRIMARY KEY (`id`),
	 KEY `master_object_id` (`master_object_id`),
	 KEY `type` (`type`)
);