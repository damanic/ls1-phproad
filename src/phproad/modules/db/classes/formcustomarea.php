<?php
namespace Db;

/**
 * Represents a form section.
 * Form sections have a title and description.
 * Objects of this class are created by {@link Db\ActiveRecord::add_form_custom_area() add_form_custom_area()} method
 * the {@link Db\ActiveRecord} class. Form area contents should be defined in
 * a partial with name _form_section_<em>id</em>.htm in the controller's views directory,
 * where <em>id</em> is an area identifier.
 *
 * @documentable
 * @author       LemonStand eCommerce Inc.
 * @package      core.classes
 */
class FormCustomArea extends FormElement
{
    /**
     * @var          string Specifies the area identifier.
     * @documentable
     */
    public string $id;

    /**
     * @param        string|null $location  Optional path of a directory containing the form area partial.
     * @documentable
     */
    public ?string $location;

    public function __construct($id, $location = null)
    {
        $this->id = $id;
        $this->location = $location;
    }
}
