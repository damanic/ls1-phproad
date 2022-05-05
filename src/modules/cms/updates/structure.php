<?php

//pages -> cms_pages
$table = Db\Structure::table('cms_pages');
$table->primaryKey('id');
$table->column('title', db_varchar)->index();
$table->column('url', db_varchar)->index();
$table->column('description', db_text);
$table->column('keywords', db_text);
$table->column('content', 'MEDIUMTEXT');
$table->column('created_user_id', db_number);
$table->column('updated_user_id', db_number);
$table->column('created_at', db_datetime);
$table->column('updated_at', db_datetime);
$table->column('template_id', db_number);
$table->column('action_reference', db_varchar, 100)->index();
$table->column('action_code', db_text);
$table->column('ajax_handlers_code', db_text);
$table->column('security_mode_id', db_varchar, 15)->defaults('everyone');
$table->column('security_redirect_page_id', db_number);
$table->column('protocol', db_varchar, 10);
$table->column('has_contentblocks', db_bool);
$table->column('label', db_varchar);
$table->column('pre_action', db_text);
$table->column('parent_id', db_number);
$table->column('navigation_visible', db_bool);
$table->column('navigation_label', db_varchar);
$table->column('navigation_sort_order', db_number)->index();
$table->column('enable_page_customer_group_filter', db_bool);
$table->column('disable_ga', db_bool);
$table->column('head', db_text);
$table->column('page_block_name_1', db_varchar, 100);
$table->column('page_block_content_1', db_text);
$table->column('page_block_name_2', db_varchar, 100);
$table->column('page_block_content_2', db_text);
$table->column('page_block_name_3', db_varchar, 100);
$table->column('page_block_content_3', db_text);
$table->column('page_block_name_4', db_varchar, 100);
$table->column('page_block_content_4', db_text);
$table->column('page_block_name_5', db_varchar, 100);
$table->column('page_block_content_5', db_text);
$table->column('is_published', db_bool)->defaults('1');
$table->column('directory_name', db_varchar);
$table->column('theme_id', db_number)->index();

//templates -> cms_templates
$table = Db\Structure::table('cms_templates');
$table->primaryKey('id');
$table->column('name', db_varchar, 100);
$table->column('description', db_varchar);
$table->column('html_code', 'MEDIUMTEXT');
$table->column('created_user_id', db_number);
$table->column('updated_user_id', db_number);
$table->column('created_at', db_datetime);
$table->column('updated_at', db_datetime);
$table->column('file_name', db_varchar);
$table->column('theme_id', db_number)->index();

//partials -> cms_partials
$table = Db\Structure::table('cms_partials');
$table->primaryKey('id');
$table->column('name', db_varchar, 100)->index();
$table->column('description', db_varchar);
$table->column('html_code', 'MEDIUMTEXT');
$table->column('created_user_id', db_number);
$table->column('updated_user_id', db_number);
$table->column('created_at', db_datetime);
$table->column('updated_at', db_datetime);
$table->column('file_name', db_varchar);
$table->column('theme_id', db_number)->index();

//cms_page_visits
$table = Db\Structure::table('cms_page_visits');
$table->primaryKey('id');
$table->column('url', db_varchar)->index();
$table->column('visit_date', db_date);
$table->column('ip', db_varchar, 45);
$table->column('page_id', db_number)->index();
$table->addKey('date_and_ip', array('visit_date', 'ip'));
$table->addKey('date_and_url', array('visit_date', 'url'));

//cms_stats_settings
$table = Db\Structure::table('cms_stats_settings');
$table->primaryKey('id');
$table->column('keep_pageviews', db_number);
$table->column('dashboard_paid_only', db_bool);
$table->column('dashboard_display_today', db_bool);
$table->column('enable_builtin_statistics', db_bool);
$table->column('ga_service_enabled', db_bool);
$table->column('ga_username', db_varchar);
$table->column('ga_password', db_varchar);
$table->column('ga_siteid', db_varchar, 100);
$table->column('ga_property_id', db_varchar, 50);
$table->column('ga_track_page_speed', db_bool);
$table->column('ga_domain_name', db_varchar);
$table->column('ga_site_speed_sample_rate', db_number);
$table->column('ga_enable_doubleclick_remarketing', db_bool);
$table->column('ga_enabled', db_bool);

//page_security_modes -> cms_page_security_modes
$table = Db\Structure::table('cms_page_security_modes');
$table->primaryKey('id');
$table->column('name', db_varchar);
$table->column('description', db_varchar);

//content_blocks -> cms_content_blocks
$table = Db\Structure::table('cms_content_blocks');
$table->primaryKey('id');
$table->column('code', db_varchar, 100);
$table->column('page_id', db_number);
$table->column('description', db_text);
$table->addKey('page_and_code', array('page_id', 'code'));

//page_customer_groups -> cms_page_customer_groups
$table = Db\Structure::table('cms_page_customer_groups');
$table->column('page_id', db_number);
$table->column('customer_group_id', db_number);
$table->addKey(null, array('page_id','customer_group_id'))->primary();

//cms_settings
$table = Db\Structure::table('cms_settings');
$table->primaryKey('id');
$table->column('enable_filebased_templates', db_bool);
$table->column('templates_dir_path', db_varchar);
$table->column('content_file_extension', db_varchar, 5)->defaults('htm');
$table->column('resources_dir_path', db_varchar);
$table->column('default_templating_engine', db_varchar, 50);

//cms_page_references
$table = Db\Structure::table('cms_page_references');
$table->primaryKey('id');
$table->column('object_class_name', db_varchar);
$table->column('object_id', db_number)->defaults('0');
$table->column('reference_name', db_varchar)->defaults('');
$table->column('page_id', db_number);
$table->addKey('object_class_name', array('object_class_name', 'object_id','reference_name'));

//cms_global_content_blocks
$table = Db\Structure::table('cms_global_content_blocks');
$table->primaryKey('id');
$table->column('content', db_text);
$table->column('created_user_id', db_number);
$table->column('updated_user_id', db_number);
$table->column('created_at', db_datetime);
$table->column('updated_at', db_datetime);
$table->column('code', db_varchar)->index();
$table->column('name', db_varchar);
$table->column('block_type', db_varchar, 10);

//cms_themes
$table = Db\Structure::table('cms_themes');
$table->primaryKey('id');
$table->column('name', db_varchar);
$table->column('code', db_varchar, 100)->index();
$table->column('description', db_text);
$table->column('author_name', db_varchar);
$table->column('author_website', db_varchar);
$table->column('is_default', db_bool);
$table->column('is_enabled', db_bool);
$table->column('agent_detection_mode', db_varchar, 10);
$table->column('agent_list', db_text);
$table->column('agent_detection_code', db_text);
$table->column('templating_engine', db_varchar, 50);
