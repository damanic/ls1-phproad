<?php
namespace Phpr;

use Phpr\SystemException;

/**
 * Base class for extension validation
 * @see Phpr\Extension
 */
class ValidateExtension extends Phpr_Extension
{
    /**
     * Executes a validation method.
     * This method is used by the PHP Road internally.
     *
     * @param  string $Method Specifies a method name.
     * @param  string $Name   Specifies a name of the field
     * @param  string $Value  Specifies a value to validate
     * @return mixed
     */
    public function execValidation($method, $name, $value)
    {
        if (method_exists($this, $method)) {
            return $this->$method($name, $value);
        }

        throw new SystemException('Validation method ' . $method . ' not found in ' . get_class($this));
    }

}
