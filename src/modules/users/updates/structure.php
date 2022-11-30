<?php

$table = Db\Structure::table('users');
$table->primaryKey('id');
$table->column('login', db_varchar, 30)->index()->unique();
$table->column('firstName', db_varchar, 255)->index();
$table->column('lastName', db_varchar, 255)->index();
$table->column('password', db_varchar, 255);
$table->column('email', db_varchar, 50)->index();
$table->column('timeZone', db_varchar, 255);
$table->column('middleName', db_varchar, 255);
$table->column('status', db_number, 11);
$table->column('shop_role_id', db_number, 11)->index();
$table->column('last_login', db_datetime);
$table->column('password_restore_hash', db_varchar, 150)->index();
$table->footprints();

$table = Db\Structure::table('users_user_permissions');
$table->primaryKey('id');
$table->column('user_id', db_number, 11)->index();
$table->column('module_id', db_varchar, 50);
$table->column('permission_name', db_varchar, 100);
$table->column('value', db_varchar, 255);
$table->addKey('user_module_permission', array('user_id', 'module_id','permission_name'));

$table = Db\Structure::table('users_groups');
$table->primaryKey('id');
$table->column('name', db_varchar, 255);
$table->column('description', db_varchar, 255);
$table->column('code', db_varchar, 100)->index();

$table = Db\Structure::table('users_user_groups');
$table->primaryKey('id');
$table->column('user_id', db_number, 11)->index();
$table->column('users_group_id', db_number, 11)->index();
$table->addKey('group_user', array('user_id', 'users_group_id'));
