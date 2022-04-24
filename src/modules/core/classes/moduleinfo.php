<?php
namespace Core;

use Phpr\Version as Version;

/**
 * Module registration class
 */
class ModuleInfo
{
    public $id;
    public $name;
    public $author;
    public $webpage;
    public $description;

    public function __construct($name, $description, $author, $webPage = null)
    {
        $this->name = $name;
        $this->author = $author;
        $this->description = $description;
        $this->webpage = $webPage;
    }

    public function getVersion()
    {
        return Version::getModuleVersion($this->id);
    }

    public function getBuild()
    {
        return Version::getModuleBuild($this->id);
    }
}
