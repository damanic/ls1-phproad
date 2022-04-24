<?php
namespace Phpr;

/**
 * PHPR module base class
 *
 * This class assists in working with modules
 */
abstract class ModuleBase
{
    // Absolute path to module
    public $dir_path;
    protected $moduleInfo = null;

    public function getModuleInfo()
    {
        if ($this->moduleInfo !== null)
            return $this->moduleInfo;

        $this->moduleInfo = $this->createModuleInfo();
        $this->moduleInfo->id = basename($this->getModulePath());

        return $this->moduleInfo;
    }

    public function getModulePath()
    {
        $reflect = new ReflectionObject($this);
        $path = dirname(dirname($reflect->getFileName()));
        return $path;
    }

    public function getId()
    {
        return $this->getModuleInfo()->id;
    }

    abstract protected function createModuleInfo();

    //
    // Subscribe to core events
    //
    public function subscribeEvents()
    {
        // Usage:
        // Phpr::$events->add_event('module:on_event_name', $this, 'local_module_method');
    }

    //
    // Subscribe to public access points
    //
    public function subscribeAccessPoints()
    {
        // Usage:
        // return array('phpr_api_access_url'=>'local_module_method');
        return array();
    }

    //
    // Subscribe to general cron table. Method must return true to indicate success.
    // Interval is in minutes.
    //
    public function subscribeCrontab()
    {
        // Usage:
        // return array('reset_counters' => array('method'=>'local_method', 'interval'=>60));
        return array();
    }
}