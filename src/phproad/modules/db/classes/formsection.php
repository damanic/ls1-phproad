<?php namespace Db;

/**
 * Represents a form section.
 * Form sections have a title and description.
 * Objects of this class are created by {@link Db_ActiveRecord::add_form_section() add_form_section()} method
 * the {@link Db_ActiveRecord} class.
 *
 * @documentable
 * @author       LSAPP
 * @package      core.classes
 */
class FormSection extends FormElement
{
    /**
     * @var          string|null Specifies the section title
     * @documentable
     */
    public $title = null;

    /**
     * @var          string|null Specifies the section description
     * @documentable
     */
    public $description = null;

    /**
     * @var          string|null Specifies the id for the html element the form section will be rendered in on the form.
     * @documentable
     */
    public $html_id;

    public function __construct($title, $description, $htmlId = null)
    {
        $this->title = $title;
        $this->description = $description;
        $this->html_id = $htmlId;
    }
}
