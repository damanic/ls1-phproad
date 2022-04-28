<?php

$table = Db\Structure::table('backend_report_dates');
$table->primaryKey('report_date', db_date);
$table->column('year', db_number)->index();
$table->column('month', db_number);
$table->column('day', db_number);
$table->column('month_start', db_date);
$table->column('month_code', db_varchar, 10)->index();
$table->column('month_end', db_date);
$table->column('year_start', db_date);
$table->column('year_end', db_date);
