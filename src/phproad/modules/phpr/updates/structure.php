<?php

$table = Db\Structure::table('phpr_cron_table');
$table->primaryKey('record_code', db_varchar, 50);
$table->column('updated_at', db_datetime);
$table->column('postpone_until', db_datetime);

$table = Db\Structure::table('phpr_cron_jobs');
$table->primaryKey('id');
$table->column('que_name', db_varchar, 255);
$table->column('handler_name', db_varchar, 100);
$table->column('param_data', db_text);
$table->column('created_at', db_datetime);
$table->column('available_at', db_datetime);
$table->column('started_at', db_datetime);
$table->column('retry', db_bool);
$table->column('attempts', db_number, 11);
$table->column('version', db_number, 11);
$table->addKey('job_index', array('handler_name'));

$table = Db\Structure::table('phpr_user_params');
$table->primaryKey('user_id')->defaults('0');
$table->primaryKey('name', db_varchar, 100);
$table->column('value', db_text);

$table = Db\Structure::table('phpr_module_params');
$table->primaryKey('module_id', db_varchar, 30);
$table->primaryKey('name', db_varchar, 100);
$table->column('value', db_text);

$table = Db\Structure::table('phpr_trace_log');
$table->primaryKey('id');
$table->column('log', db_varchar)->index();
$table->column('message', db_text);
$table->column('details', 'mediumtext');
$table->column('record_datetime', db_datetime);

$table = Db\Structure::table('phpr_generic_binds');
$table->primaryKey('id');
$table->column('primary_id', db_number, 11)->index();
$table->column('secondary_id', db_number, 11)->index();
$table->column('field_name', db_varchar, 100)->index();
$table->column('class_name', db_varchar, 100)->index();

