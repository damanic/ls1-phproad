<?php

namespace Db;

use Phpr;
use Phpr\ModuleManager;
use Phpr\ApplicationException;
use Db\Helper as DbHelper;

/**
 * PHPR Update manager
 * Manages module update chain
 */
class UpdateManager
{
    private static $versions = null;
    private static $updates = null;
    public static $ignoreSqlFileErrors = false;

    // Updates all modules
    public static function update()
    {
        self::createMetaTable();

        $all_modules = self::getModulesAndPaths();
        $has_updates = false;

        // 1. Spool up the database schema and look for updates
        //

        foreach ($all_modules as $moduleId => $base_path) {
            $current_db_version = self::getDbVersion($moduleId);

            // Get the latest version passed by reference
            $last_dat_version = null;
            self::getDatVersions($moduleId, $last_dat_version, $base_path);

            if ($current_db_version != $last_dat_version) {
                $has_updates = true;
            }

            self::applyDbStructure($base_path, $moduleId);
        }

        // 2. If updates are found, commit all the spooled schema changes
        //

        if ($has_updates) {
            Structure::saveAll();
        }

        // 3. Apply database version upgrade files (to be deprecated in future)
        //

        foreach ($all_modules as $moduleId => $base_path) {
            self::updateModule($moduleId, $base_path);
        }

        // Clear cache
        //

        if ($has_updates) {
            ActiveRecord::clear_describe_cache();
        }
    }

    /**
     * Returns all modules (including system) and their paths
     * respecting the UPDATE SEQUENCE defined in config
     */
    private static function getModulesAndPaths()
    {
        $found_modules = array();

        // Find system modules
        //

        $modules_path = PATH_SYSTEM . DS . PHPR_MODULES;
        $iterator = new \DirectoryIterator($modules_path);

        foreach ($iterator as $dir) {
            if (!$dir->isDir() || $dir->isDot()) {
                continue;
            }

            $moduleId = $dir->getFilename();

            $found_modules[$moduleId] = PATH_SYSTEM;
        }

        // Update application modules
        //

        $modules = ModuleManager::getModules(true);
        $moduleIds = array();

        foreach ($modules as $module) {
            $id = mb_strtolower($module->getModuleInfo()->id);
            $moduleIds[$id] = 1;
        }

        $sequence = array_flip(Phpr::$config->get('UPDATE_SEQUENCE', array()));

        if (count($sequence)) {
            $updated_module_ids = $sequence;
            foreach ($moduleIds as $moduleId => $value) {
                if (!isset($sequence[$moduleId])) {
                    $updated_module_ids[$moduleId] = 1;
                }
            }

            $moduleIds = $updated_module_ids;
        }

        $moduleIds = array_keys($moduleIds);

        foreach ($moduleIds as $moduleId) {
            if (!isset($modules[$moduleId])) {
                continue;
            }

            $module = $modules[$moduleId];
            $module_path = DS . PHPR_MODULES . DS . $moduleId;
            $base_path = str_replace($module_path, '', $module->getModulePath());

            $found_modules[$moduleId] = $base_path;
        }

        return $found_modules;
    }

    public static function resetCache()
    {
        self::$versions = null;
    }

    /**
     * Returns versions of all modules installed in the system
     */
    public static function getVersions()
    {
        $result = array();

        $modules = ModuleManager::getModules(false);
        foreach ($modules as $module) {
            $moduleId = $module->getModuleInfo()->id;
            $version = self::getDbVersion($moduleId);
            $result[$moduleId] = $version;
        }

        return $result;
    }

    /**
     * Checks whether a version does exist in the module update history.
     * @param string $moduleId Specifies a module identifier.
     * @param string @version_str Specifies a version string.
     * @return boolean Returns true if the version was found in the module update history. Returns false otherwise.
     */
    public static function moduleVersionExists($moduleId, $versionStr)
    {
        $bind = array(
            'module_id' => $moduleId,
            'version_str' => $versionStr
        );
        return (DbHelper::scalar(
            'select count(*) from phpr_module_update_history where module_id=:module_id and version_str=:version_str',
            $bind
        ) > 0);
    }

    // Updates a single module
    // $base_path can specify the exact location
    public static function updateModule($moduleId, $basePath = null)
    {
        $basePath = $basePath === null ? PATH_APP : $basePath;

        $last_dat_version = null;
        $dat_versions = self::getDatVersions($moduleId, $last_dat_version, $basePath);

        // Apply new database/structure/php updates
        //

        $db_update_result = false;

        $current_db_version = self::getDbVersion($moduleId);
        $last_db_version_index = self::getDbUpdateIndex($current_db_version, $dat_versions);

        foreach ($dat_versions as $index => $update_info) {
            if ($update_info['type'] == 'update-reference') {
                $db_update_result = self::applyDbUpdate(
                    $basePath,
                    $moduleId,
                    $update_info['reference']
                ) || $db_update_result;
            } elseif ($update_info['type'] == 'version-update') {
                // Apply updates from references specified in the version string
                foreach ($update_info['references'] as $reference) {
                    $db_update_result = self::applyDbUpdate($basePath, $moduleId, $reference) || $db_update_result;
                }

                // Apply updates with names matching the version number
                if ($index > $last_db_version_index && $last_db_version_index !== -2) {
                    if (strlen($update_info['build'])) {
                        $db_update_result = self::applyDbUpdate(
                            $basePath,
                            $moduleId,
                            $update_info['build']
                        ) || $db_update_result;
                    } else {
                        $db_update_result = self::applyDbUpdate(
                            $basePath,
                            $moduleId,
                            $update_info['version']
                        ) || $db_update_result;
                    }
                }
            }
        }

        // Increase the version number and add new version records to the version history table
        if ($current_db_version != $last_dat_version) {
            self::setDbVersion($current_db_version, $last_dat_version, $dat_versions, $moduleId);
        }

        return $db_update_result;
    }

    /**
     * Applies module update file(s).
     * @param string $basePath Base module directory.
     * @param string $moduleId Module identifier.
     * @param string $updateId Update identifier.
     * @return boolean Returns true if any updates have been applied. Returns false otherwise.
     */
    protected static function applyDbUpdate($basePath, $moduleId, $updateId)
    {
        // If the update has already been applied, return false
        if (in_array($updateId, self::getModuleAppliedUpdates($moduleId))) {
            return false;
        }

        $result = false;

        // Apply PHP update file
        $update_path = $basePath . DS . PHPR_MODULES . DS . $moduleId . DS . 'updates' . DS . $updateId . '.php';
        if (file_exists($update_path)) {
            $result = true;
            include $update_path;
        }

        // Apply SQL update file
        $update_path = $basePath . DS . PHPR_MODULES . DS . $moduleId . DS . 'updates' . DS . $updateId . '.sql';
        if (file_exists($update_path)) {
            $result = true;
            try {
                DbHelper::executeSqlFromFile($update_path);
            } catch (\Exception $e ){
                if(self::$ignoreSqlFileErrors){
                    traceLog('Failed DB update '.$update_path.' : '. $e->getMessage());
                } else {
                    throw $e;
                }
            }
        }

        // Register the applied update in the database and in the internal cache
        if ($result) {
            self::registerAppliedModuleUpdate($moduleId, $updateId);
        }

        return $result;
    }

    public static function applyDbStructure($base_path, $moduleId)
    {
        Structure::$moduleId = $moduleId;

        $structure_file = $base_path . DS . PHPR_MODULES . DS . $moduleId . DS . 'updates' . DS . 'structure.php';
        if (file_exists($structure_file)) {
            include $structure_file;
        }

        Structure::$moduleId = null;
    }

    public static function createMetaTable()
    {
        $tables = DbHelper::listTables();
        if (!in_array('phpr_module_versions', $tables)) {
            DbHelper::executeSqlFromFile(PATH_SYSTEM . '/' . PHPR_MODULES . '/phpr/updates/bootstrap.sql');
            include_once(PATH_SYSTEM . '/' . PHPR_MODULES . '/phpr/updates/migrate_v1.php');
        }
    }

    /**
     * Returns version of a module stored in the database.
     * @param string $moduleId Specifies the module identifier.
     * @return string Returns the module version.
     */
    public static function getDbVersion($moduleId)
    {
        if (self::$versions === null) {
            $versions = DbHelper::queryArray('select module_id, version_str as version from phpr_module_versions order by id');
            self::$versions = array();

            foreach ($versions as $version_info) {
                $id = $version_info['module_id'];
                self::$versions[$id] = $version_info['version'];
            }
        }

        if (array_key_exists($moduleId, self::$versions)) {
            return self::$versions[$moduleId];
        }

        $bind = array(
            'module_id' => $moduleId,
            'date' => gmdate('Y-m-d h:i:s')
        );

        DbHelper::query(
            'insert into phpr_module_versions(module_id, date, `version`) values (:module_id, :date, 0)',
            $bind
        );

        return 0;
    }

    /**
     * Updates module version history and its version in the database.
     * @param string $current_db_version Current module version number stored in the database.
     * @param string $last_dat_version Latest module version specified in the module version.dat file.
     * @param mixed $dat_versions Parsed versions information from the module version.dat file.
     * @param string $moduleId Module identifier.
     */
    private static function setDbVersion($current_db_version, $last_dat_version, &$dat_versions, $moduleId)
    {
        if (self::$versions === null) {
            self::$versions = array();
        }

        // Update the module version number
        //

        $bind = array('version_str' => $last_dat_version, 'module_id' => $moduleId);
        DbHelper::query(
            'update phpr_module_versions set `version`=null, version_str=:version_str where module_id=:module_id',
            $bind
        );

        self::$versions[$moduleId] = $last_dat_version;

        // Add version history records
        //

        $last_db_version_index = self::getDbUpdateIndex($current_db_version, $dat_versions);
        if ($last_db_version_index !== -2) {
            $last_version_index = count($dat_versions) - 1;
            $start_index = $last_db_version_index + 1;
            if ($start_index <= $last_version_index) {
                for ($index = $start_index; $index <= $last_version_index; $index++) {
                    $version_info = $dat_versions[$index];

                    if ($version_info['type'] != 'version-update') {
                        continue;
                    }

                    DbHelper::query(
                        'insert
							into phpr_module_update_history(date, module_id, `version`, description, version_str)
							values(:date, :module_id, :version, :description, :version_str)',
                        array(
                            'date' => gmdate('Y-m-d h:i:s'),
                            'module_id' => $moduleId,
                            'version' => $version_info['build'],
                            'description' => $version_info['description'],
                            'version_str' => $version_info['version']
                        )
                    );
                }
            }
        }
    }

    /**
     * Returns index of a record in the version.dat file which corresponds to the latest version of the module stored in the database.
     * @param string $current_db_version Current module version number stored in the database.
     * @param mixed $dat_versions Parsed versions information from the module version.dat file.
     * @return integer Returns the version record index. Returns -1 if a matching record was not found in the database.
     */
    public static function getDbUpdateIndex($current_db_version, &$dat_versions)
    {
        foreach ($dat_versions as $index => $version_info) {
            if ($version_info['type'] == 'version-update') {
                if ($version_info['version'] == $current_db_version) {
                    return $index;
                }
            }
        }

        if ($current_db_version) {
            return -2;
        }

        return -1;
    }

    /**
     * Returns full version information from a module's version.dat file.
     * Returns a list of versions and update references in the following format:
     * array(
     *   0=>array('type'=>'version-update', 'version'=>'1.1.1', 'build'=>111, 'description'=>'Version description', 'references'=>array('abc123de45', 'abc123de46')),
     *   1=>array('type'=>'update-reference', 'reference'=>'abc123de47')
     * )
     * @param string $moduleId Specifies the module identifier.
     * @param string $last_version Reference to the latest version in the version.dat file.
     * @param string $base_path Base module path, defaults to the application root directory.
     * @return array Returns array of application versions and references to the database update files.
     */
    public static function getDatVersions($moduleId, &$last_version, $base_path = null)
    {
        $base_path = $base_path === null ? PATH_APP : $base_path;
        $versions_path = $base_path . DS . PHPR_MODULES . DS . $moduleId . DS . 'updates' . DS . 'version.dat';
        if (!file_exists($versions_path)) {
            return array();
        }

        return self::parseDatFile($versions_path, $last_version);
    }

    /**
     * Parses a .dat file and returns full version information it contains.
     * Returns a list of versions and update references in the following format:
     * array(
     *   0=>array('type'=>'version-update', 'version'=>'1.1.1', 'build'=>111, 'description'=>'Version description', 'references'=>array('abc123de45', 'abc123de46')),
     *   1=>array('type'=>'update-reference', 'reference'=>'abc123de47')
     * )
     * @param string $file_path Path to the file to parse.
     * @param string $last_version Reference to the latest version in the version.dat file.
     * @return array Returns array of application versions and references to the database update files.
     */
    public static function parseDatFile($file_path, &$last_version)
    {
        $last_version = null;

        if (!file_exists($file_path)) {
            return array();
        }

        $contents = file_get_contents($file_path);

        // Normalize line-endings and split the file content
        //

        $contents = str_replace("\r\n", "\n", $contents);
        $update_list = preg_split("/^\s*(#)|^\s*(@)/m", $contents, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

        // Analyze each update and extract its type and description
        //

        $length = count($update_list);
        $result = array();

        for ($index = 0; $index < $length;) {
            $update_type = $update_list[$index];
            $update_content = $update_list[$index + 1];

            if ($update_type == '@') {
                // Parse update references
                //

                $references = preg_split('/\|\s*@/', $update_content);
                foreach ($references as $reference) {
                    $result[] = array('type' => 'update-reference', 'reference' => trim($reference));
                }
            } elseif ($update_type == '#') {
                // Parse version strings
                //

                $pos = mb_strpos($update_content, ' ');

                if ($pos === false) {
                    throw new ApplicationException('Error parsing version file (' . $file_path . '). Version string should have a description: ' . $update_content);
                }

                $version_info = trim(mb_substr($update_content, 0, $pos));
                $description = trim(mb_substr($update_content, $pos + 1));

                // Expected version/update notations:
                // 2
                // 2|0.0.2
                // 2|@abc123de46
                // 0.0.2|@abc123de45|@abc123de46
                //

                $version_info_parts = explode('|@', $version_info);

                $version_number = self::extractVersionNumber($version_info_parts[0]);
                $build_number = self::extractBuildNumber($version_info_parts[0]);
                $references = array();

                if (($cnt = count($version_info_parts)) > 1) {
                    for ($ref_index = 1; $ref_index < $cnt; $ref_index++) {
                        $references[] = $version_info_parts[$ref_index];
                    }
                }

                $last_version = $version_number;

                $result[] = array(
                    'type' => 'version-update',
                    'version' => $version_number,
                    'build' => $build_number,
                    'description' => $description,
                    'references' => $references
                );
            }

            $index += 2;
        }

        return $result;
    }

    /**
     * Extracts version number from a version string, which can also contain a build number.
     * Returns "1.0.2" for 2|1.0.2.
     * @param string $versionString Version string.
     * @return string Returns the version string.
     */
    public static function extractVersionNumber($version_string)
    {
        $parts = explode('|', $version_string);
        if (count($parts) == 2) {
            return trim($parts[1]);
        }

        if (strpos($parts[0], '.') === false) {
            return '1.0.' . trim($parts[0]);
        }

        return trim($parts[0]);
    }

    /**
     * Extracts build number from a version string (backward compatibility).
     * Returns "2" for 2|1.0.2. Returns null for 1.0.2.
     * @param string $versionString Version string.
     * @return string Returns the build number.
     */
    public static function extractBuildNumber($version_string)
    {
        $parts = explode('|', $version_string);
        if (count($parts) == 2) {
            return trim($parts[0]);
        }

        if (strpos($parts[0], '.') !== false) {
            return null;
        }

        return trim($parts[0]);
    }

    /**
     * Returns a list of update identifiers which have been applied to a specified module.
     * @param string $moduleId Specified the module identifier.
     * @return array Returns a list of applied update identifiers.
     */
    public static function getModuleAppliedUpdates($moduleId)
    {
        if (self::$updates === null) {
            self::$updates = array();

            $update_list = DbHelper::queryArray('select * from phpr_module_applied_updates');
            foreach ($update_list as $update_info) {
                if (!array_key_exists($update_info['module_id'], self::$updates)) {
                    self::$updates[$update_info['module_id']] = array();
                }

                self::$updates[$update_info['module_id']][] = $update_info['update_id'];
            }
        }

        if (!isset(self::$updates[$moduleId])) {
            return array();
        }

        return self::$updates[$moduleId];
    }

    /**
     * Adds update to the list of applied module updates.
     * @param string $moduleId Specified the module identifier.
     * @param string $update_id Specified the update identifier.
     */
    protected static function registerAppliedModuleUpdate($moduleId, $update_id)
    {
        if (self::$updates === null) {
            self::$updates = array();
        }

        if (!isset(self::$updates[$moduleId])) {
            self::$updates[$moduleId] = array();
        }

        self::$updates[$moduleId][] = $update_id;
        $bind = array(
            'module_id' => $moduleId,
            'update_id' => $update_id,
            'created_at' => gmdate('Y-m-d h:i:s')
        );
        DbHelper::query('insert into phpr_module_applied_updates (module_id, update_id, created_at) 
			values (:module_id, :update_id, :created_at)', $bind);
    }

    /**
     * @deprecated
     */
    public static function createMetadata()
    {
        return self::createMetaTable();
    }
}
