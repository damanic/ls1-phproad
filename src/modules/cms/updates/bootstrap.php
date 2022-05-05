<?php

use Db\Helper as DbHelper;

/**
 * Migrate Legacy Tables If Found
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

foreach ($tableMigrations as $legacyTable => $table) {
    if (in_array($legacyTable, $tables)) {
        //CLONE LEGACY TABLE IF NO TABLE EXISTS
        if (!in_array($table, $tables)) {
            try {
                DbHelper::query('CREATE TABLE `' . $table . '` AS SELECT * FROM `' . $legacyTable . '` ');
            } catch (\Exception $e) {
                //continue
            }
        }

        //COPY DATA AND DROP LEGACY TABLE
        if (in_array($table, $tables)) {
            if (DbHelper::scalar('SELECT COUNT(*) FROM `'.$table.'`') == 0) {
                try {
                    DbHelper::query('INSERT INTO `'.$table.'` SELECT * FROM `'.$legacyTable.'` ');
                    DbHelper::query('DROP TABLE `'.$legacyTable.'`');
                } catch (\Exception $e) {
                    //continue
                }
            }
        }
    }
}
