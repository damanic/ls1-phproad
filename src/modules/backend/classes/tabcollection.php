<?php
namespace Backend;

/**
 * Represents a collection if GUI tabs of a specific module
 */
class TabCollection
{
    public $tabs = array();
    private $moduleId;

    public function __construct($moduleId)
    {
        $this->moduleId = $moduleId;
    }

    /**
     * Adds a tab to the collection
     * @param string $id Specifies a tab identifier
     * @param string $caption Specifies the tab caption
     * @param string $url Specifies an URL corresponding the tab
     * @param int $position Specifies the tab position in the tab bar
     * @return Tab Returns a tab object
     */
    public function tab($id, $caption, $url, $position)
    {
        return $this->tabs[] = new Tab($id, $caption, $url, $position, $this->moduleId);
    }
}
