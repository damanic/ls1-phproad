<?php namespace Db;

use Phpr;
use Phpr\SystemException;

/**
 * Represents a form partial.
 * Objects of this class are created by {@link ActiveRecord::add_form_partial() add_form_partial()} method
 * the {@link ActiveRecord} class.
 *
 * @documentable
 * @author       LSAPP
 * @package      core.classes
 */
class FormPartial extends FormElement
{
    /**
     * @var          string Specifies the partial file path.
     * @documentable
     */
    public string $path;

    public function __construct($path)
    {
        $this->path = $path;
    }
}
