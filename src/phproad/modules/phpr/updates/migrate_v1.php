<?php

$tables = Db\Helper::listTables();

//PHPR

//userparams -> phpr_user_params
if (in_array('userparams', $tables) && in_array('phpr_user_params', $tables)) {
    if(Db\Helper::scalar('SELECT COUNT(*) FROM `phpr_user_params`') == 0){
        Db\Helper::query('INSERT INTO `phpr_user_params` SELECT * FROM `userparams`');
    }
}

//@moduleparams -> phpr_module_params
if (in_array('moduleparams', $tables) && in_array('phpr_module_params', $tables)) {
    if(Db\Helper::scalar('SELECT COUNT(*) FROM `phpr_module_params`') == 0){
        Db\Helper::query('INSERT INTO `phpr_module_params` SELECT * FROM `moduleparams`');
    }
}

//core_versions ->  phpr_module_versions
if (in_array('core_versions', $tables) && in_array('phpr_module_versions', $tables)) {
    if(Db\Helper::scalar('SELECT COUNT(*) FROM `phpr_module_versions`') == 0){
        Db\Helper::query('INSERT INTO `phpr_module_versions` SELECT * FROM `core_versions`');
    }
}

//core_update_history ->  phpr_module_update_history
if (in_array('core_update_history', $tables) && in_array('phpr_module_update_history', $tables)) {
    if(Db\Helper::scalar('SELECT COUNT(*) FROM `phpr_module_update_history`') == 0){
        Db\Helper::query('INSERT INTO `phpr_module_update_history` SELECT * FROM `core_update_history`');
    }
}

//core_applied_updates -> phpr_module_applied_updates
if (in_array('core_applied_updates', $tables) && in_array('phpr_module_applied_updates', $tables)) {
    if(Db\Helper::scalar('SELECT COUNT(*) FROM `phpr_module_applied_updates`') == 0){
        Db\Helper::query('INSERT INTO `phpr_module_applied_updates` SELECT * FROM `core_applied_updates`');
    }
}
