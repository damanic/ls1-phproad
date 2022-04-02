<?php
namespace Phpr;
/**
 * Class to define a module
 */

class ModuleDetail
{
	public $id;
	
	public $name;
	public $author;
	public $url;
	public $description;
    public $webpage; //deprecated

	public function __construct($name, $description, $author, $url = null)
	{
		$this->name = $name;
		$this->author = $author;
		$this->description = $description;
		$this->url = $url;
        $this->webpage = $url;
	}
	
	public function getVersion()
	{
		return Phpr_Version::getModuleVersion($this->id);
	}
	
	public function getBuild()
	{
		return Phpr_Version::getModuleBuild($this->id);
	}
}
