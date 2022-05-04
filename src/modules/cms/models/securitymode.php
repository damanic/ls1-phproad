<?php
namespace Cms;

use Db\ActiveRecord;

class SecurityMode extends ActiveRecord
{
    const everyone = 'everyone';
    const customers = 'customers';
    const guests = 'guests';
        
    public $table_name = 'page_security_modes';
}
