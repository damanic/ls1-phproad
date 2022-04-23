<?php
namespace Core;

use Phpr;
use Phpr\DateTime as PhprDateTime;
use Db\ActiveRecord;
use Db\Helper as DbHelper;
use Users\User as User;


class EulaInfo extends ActiveRecord
{
    public $table_name = 'core_eula_info';

    public static function create()
    {
        return new self();
    }

    public static function update_info($text)
    {
        $obj = self::get();
                
        $obj->agreement_text = $text;
            
        $current_user = Phpr::$security->getUser();
        $obj->accepted_by = $current_user ? $current_user->id : null;
        $obj->accepted_on = PhprDateTime::now();
        $obj->save();

        DbHelper::query('delete from core_eula_unread_users');
        $users = User::listAdministrators();
        foreach ($users as $user) {
            if ($current_user && $current_user->id == $user->id) {
                continue;
            }

            DbHelper::query(
                'insert into core_eula_unread_users(user_id) values (:user_id)',
                array(
                    'user_id'=>$user->id
                )
            );
        }
    }
        
    public static function get()
    {
        $obj = self::create();
        if ($existing = $obj->find()) {
            return $existing;
        }
                
        return $obj;
    }
        
    public function get_accepted_user_name()
    {
        if (!$this->accepted_by) {
            return null;
        }
            
        $user = User::create()->find($this->accepted_by);
        if (!$user) {
            return 'Unknown user';
        }
                
        return $user->name;
    }
        
    public static function is_unread($user_id)
    {
        $read = DbHelper::scalar(
            'select count(*) from core_eula_unread_users where user_id=:id',
            array(
                'id'=>$user_id
            )
        );
        return $read > 0;
    }


    public static function mark_read($user_id)
    {
        DbHelper::query(
            'delete from core_eula_unread_users where user_id=:id',
            array(
                'id'=>$user_id
            )
        );
    }
}
