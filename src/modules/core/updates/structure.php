<?php

$table = Db\Structure::table('core_configuration_records');
$table->primaryKey('id');
$table->column('record_code', db_varchar)->index();
$table->column('config_data', db_text);

$table = Db\Structure::table('core_metrics');
$table->primaryKey('id');
$table->column('total_amount', db_float)->precision(8);
$table->column('total_order_num', db_number);
$table->column('page_views', db_number);
$table->column('updated', db_date);
$table->column('update_lock', db_datetime);

$table = Db\Structure::table('core_eula_info');
$table->primaryKey('id');
$table->column('agreement_text', db_text);
$table->column('accepted_by', db_number);
$table->column('accepted_on', db_datetime);

$table = Db\Structure::table('core_eula_unread_users');
$table->primaryKey('user_id');
