<?php

$table = Db\Structure::table('system_backup_archives');
$table->primaryKey('id');
$table->column('path', db_varchar);
$table->column('created_at', db_datetime);
$table->column('created_user_id', db_number);
$table->column('status_id', db_number);
$table->column('comment', db_text);
$table->column('error_message', db_text);

$table = Db\Structure::table('system_backup_settings');
$table->primaryKey('id');
$table->column('backup_path', db_varchar);
$table->column('backup_interval', db_number);
$table->column('num_files_to_keep', db_number);
$table->column('notify_administrators', db_bool);
$table->column('backup_on_login', db_bool);
$table->column('archive_uploaded_dir', db_bool);

$table = Db\Structure::table('system_colortheme_settings');
$table->primaryKey('id');
$table->column('logo_border', db_bool);
$table->column('header_text', db_varchar, 100);
$table->column('theme_id', db_varchar, 30);
$table->column('hide_footer_logos', db_bool);
$table->column('footer_text', db_text);

$table = Db\Structure::table('system_compound_email_vars');
$table->primaryKey('id');
$table->column('code', db_varchar, 50)->index();
$table->column('content', db_text);
$table->column('scope', db_varchar, 50)->index();
$table->column('description', db_varchar);

$table = Db\Structure::table('system_email_layouts');
$table->primaryKey('id');
$table->column('code', db_varchar, 50)->index();
$table->column('content', db_text);
$table->column('css', db_text);
$table->column('name', db_varchar);

$table = Db\Structure::table('system_email_templates');
$table->primaryKey('id');
$table->column('code', db_varchar, 100)->index()->unique();
$table->column('subject', db_varchar);
$table->column('content', db_text);
$table->column('description', db_text);
$table->column('is_system', db_bool);
$table->column('reply_to_mode', db_varchar, 10)->defaults('default');
$table->column('reply_to_address', db_varchar, 100);
$table->column('allow_recipient_block', db_bool);

$table = Db\Structure::table('system_htmleditor_config');
$table->primaryKey('id');
$table->column('code', db_varchar, 100)->index();
$table->column('controls_row_1', db_text);
$table->column('controls_row_2', db_text);
$table->column('controls_row_3', db_text);
$table->column('content_css', db_varchar);
$table->column('block_formats', db_text);
$table->column('custom_styles', db_text);
$table->column('font_sizes', db_text);
$table->column('font_colors', db_text);
$table->column('background_colors', db_text);
$table->column('allow_more_colors', db_bool);
$table->column('module', db_varchar, 100);
$table->column('description', db_text);
$table->column('valid_elements', db_text);
$table->column('valid_child_elements', db_text);
$table->column('default_height', db_varchar, 11);
$table->addKey('code_module', array('code', 'module'));

$table = Db\Structure::table('system_login_log');
$table->primaryKey('id');
$table->column('user_id', db_number)->index();
$table->column('created_at', db_datetime)->index();
$table->column('ip', db_varchar, 45);

$table = Db\Structure::table('system_mail_settings');
$table->primaryKey('id');
$table->column('smtp_address', db_varchar);
$table->column('smtp_authorization', db_bool);
$table->column('smtp_user', db_text);
$table->column('smtp_password', db_text);
$table->column('sender_name', db_varchar, 50)->defaults('LSAPP');
$table->column('sender_email', db_varchar, 50);
$table->column('smtp_port', db_number)->defaults(25);
$table->column('smtp_ssl', db_bool);
$table->column('send_mode', db_varchar, 20);
$table->column('sendmail_path', db_varchar);
$table->column('templating_engine', db_varchar, 50)->defaults('php');
$table->column('allow_recipient_blocking', db_bool);
