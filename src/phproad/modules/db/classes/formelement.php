<?php namespace Db;

/**
 * Base class for all form elements
 *
 * @documentable
 * @author       LemonStand eCommerce Inc.
 * @package      core.classes
 */
class FormElement
{
    /**
     * @var          string|null Specifies the form tab name.
     * @documentable
     */
    public ?string $tab;

    /**
     * @var          bool Makes the element invisible in the form preview.
     * @documentable
     */
    public bool $noPreview = false;

    /**
     * @var          bool Hides the element from forms.
     * @documentable
     */
    public bool $noForm = false;

    /**
     * @var          int Specifies the element position in a form.
     * By default this parameter is assigned automatically.
     * @documentable
     */
    public int $sortOrder = 0;

    /**
     * @var          bool Determines whether the element should be placed to the collapsable form area.
     * @documentable
     */
    public bool $collapsable = false;

    /**
     * Specifies a caption of the tab to place the field to.
     * If you use tabs, you should call this method for all form field in the model.
     *
     * @documentable
     * @param        string $tabCaption Specifies the tab caption.
     * @return       FormElement Returns the updated form element object.
     */
    public function tab($tabCaption)
    {
        $this->tab = $tabCaption;
        return $this;
    }

    /**
     * Hides the element from the form preview.
     *
     * @documentable
     * @return       FormElement Returns the updated form element object.
     */
    public function noPreview()
    {
        $this->noPreview = true;
        return $this;
    }

    /**
     * Hides the element from the form.
     *
     * @documentable
     * @return       FormElement Returns the updated form element object.
     */
    public function noForm()
    {
        $this->noForm = true;
        return $this;
    }

    /**
     * Sets the element position on the form.
     * For elements without any position  specified, the position is calculated automatically,
     * basing on the {@link ActiveRecord::add_form_field() add_form_field()} method call order.
     * The first element sort order value is 10, second's - 20 and so on.
     *
     * @documentable
     * @param        int $value Specifies the element sort order.
     * @return       FormElement Returns the updated form element object.
     */
    public function sortOrder($value)
    {
        $this->sortOrder = $value;
        return $this;
    }

    /**
     * Places the element to the form or tab collapsable area.
     *
     * @documentable
     * @param        bool $value Determines whether the element should be placed to the collapsable area.
     * @return       FormElement Returns the updated form element object.
     */
    public function collapsable($value = true)
    {
        $this->collapsable = $value;
        return $this;
    }
}
