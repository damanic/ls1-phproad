<?php

namespace Users;

use Db\ActiveRecord;

class Group extends ActiveRecord
{
    public const ADMIN = 'administrator';

    public $table_name = "users_groups";
    public $primary_key = 'id';
    public $has_and_belongs_to_many = array("users"=>array('class_name'=>'Users\User') );

    /**
     * Group database identifier.
     * @var int
     */
    public $id;

    /**
     * Group name.
     * @var string
     */
    public $name = '';
}
