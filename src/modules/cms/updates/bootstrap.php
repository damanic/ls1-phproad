<?php

use Db\Helper as DbHelper;
use Db\UpdateManager;
use Db\Structure;
use Cms\SecurityMode;

/**
 * LS1 Migration: Migrate Legacy Tables If Found
 */

$tables = DbHelper::listTables();
$tableMigrations = [
    'pages' => 'cms_pages',
    'templates' => 'cms_templates',
    'partials' => 'cms_partials',
    'page_security_modes' => 'cms_page_security_modes',
    'content_blocks' => 'cms_content_blocks',
    'page_customer_groups' => 'cms_page_customer_groups',
];
$migrationsPerformed = false;

foreach ($tableMigrations as $legacyTable => $table) {
    if (in_array($legacyTable, $tables)) {
        $archiveTableName = '__archived__'.$legacyTable;

        //If LEGACY TABLE IS EMPTY, drop and continue
        $legacyTableRows = DbHelper::scalar('SELECT COUNT(*) FROM `'.$legacyTable.'`');
        if (!$legacyTableRows) {
            try {
                DbHelper::query('DROP TABLE `'.$legacyTable.'`');
            } catch (\Exception $e) {
                //ignore
            }
            continue;
        }

        //DROP ALREADY CREATED TABLE IF EXISTS
        $newTableExists = in_array($table, $tables);
        if ($newTableExists) {
            try {
                DbHelper::query('DROP TABLE `' . $table . '`');
                $newTableExists = false;
            } catch (\Exception $e) {
                //ignore
            }
        }

        //RECREATE TABLE FROM LEGACY (inherit custom fields)
        if (!$newTableExists) {
            try {
                DbHelper::query('CREATE TABLE `' . $table . '` LIKE `' . $legacyTable . '` ');
                $migrationsPerformed = true;
                $newTableExists = true;
            } catch (\Exception $e) {
                //ignore
            }
        }

        //COPY DATA IF NEW TABLE IS EMPTY
        $newTableRows = DbHelper::scalar('SELECT COUNT(*) FROM `'.$table.'`');
        if ($newTableExists && !$newTableRows) {
            try {
                DbHelper::query('INSERT INTO `'.$table.'` SELECT * FROM `'.$legacyTable.'`');
                DbHelper::query('RENAME TABLE `'.$legacyTable.'` TO `'.$archiveTableName.'`');
            } catch (\Exception $e) {
                //ignore
            }
        }
    }
}

if ($migrationsPerformed) {
    //LS1 Migration: Update legacy CMS page security MODE ids
    $securityModes = SecurityMode::create()->find_all();
    foreach ($securityModes as $securityMode) {
        if ($securityMode->code) {
            continue;
        }
        $modeCode = null;
        if (stristr($securityMode->name, 'All')) {
            $modeCode = SecurityMode::EVERYONE;
        }
        if (stristr($securityMode->name, 'Guests')) {
            $modeCode = SecurityMode::GUESTS;
        }
        if (stristr($securityMode->name, 'Customers')) {
            $modeCode = SecurityMode::CUSTOMERS;
        }
        if ($modeCode) {
            $securityMode->code = $modeCode;
            DbHelper::query(
                'UPDATE cms_pages SET security_mode_id = :mode_id WHERE security_mode_id = :mode_code',
                [
                    'mode_id' => $securityMode->id,
                    'mode_code' => $modeCode
                ]
            );
        }
    }

    //Update to LS2 structure
    UpdateManager::applyDbStructure(PATH_APP, 'cms');
    Structure::saveAll();

    //LS1 Migration: Save security mode codes to security mode records
    foreach ($securityModes as $securityMode) {
            $securityMode->save(['code' => $securityMode->code]);
    }
}
