<?php
namespace Cms;

use Db\ActiveRecord;

class SecurityMode extends ActiveRecord
{
    const EVERYONE = 'everyone';
    const CUSTOMERS = 'customers';
    const GUESTS = 'guests';
        
    public $table_name = 'cms_page_security_modes';

    public static function create(){
        return new self();
    }
}
