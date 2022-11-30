<?php

use Db\Helper as DbHelper;
use Db\UpdateManager;
use Db\Structure;

/**
 * Migrate Legacy Tables If Found
 */

$existingTables = DbHelper::listTables();
$tableMigrations = [
//    'users' => 'users_user', @todo direct references to the user table need to be removed (eg. db footprints)!
    'groups' => 'users_groups',
    'groups_users' => 'users_user_groups',
    'user_permissions' => 'users_user_permissions',
];
$migrationsPerformed = false;

foreach ($tableMigrations as $legacyTable => $table) {
    if (in_array($legacyTable, $existingTables)) {
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
        $newTableExists = in_array($table, $existingTables);
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
    UpdateManager::applyDbStructure(PATH_APP, 'users');
    Structure::saveAll();
}
