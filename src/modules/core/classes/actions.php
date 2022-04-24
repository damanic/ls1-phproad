<?php
namespace Core;

use Cms\ActionScope;

class Actions extends ActionScope
{
    /**
     * This handler does nothing on the server side.
     * It can be used for updating partials on the front-end
     */
    public function on_null()
    {
    }
}
