<?php

namespace Core;

class Actions extends Cms\ActionScope
{
    /**
     * This handler does nothing on the server side.
     * It can be used for updating partials on the front-end
     */
    public function on_null()
    {
    }
}
