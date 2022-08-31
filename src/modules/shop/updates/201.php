<?php

use Db\Helper as DbHelper;

    $states = [
        'Northern Territory'=>'AU-NT',
        'Australian Capital Territory'=>'AU-ACT'
    ];

    $country_id = DbHelper::scalar('select id from shop_countries where code_3=:code_3', ['code_3'=>'AUS']);
    if ($country_id) {
        foreach ($states as $state_name => $state_code) {
            $states_cnt = DbHelper::scalar(
                'select count(*) from shop_states where country_id=:country_id and code=:state_code',
                ['country_id'=>$country_id, 'state_code'=>$state_code]
            );

            if (!$states_cnt) {
                DbHelper::query(
                    'insert into shop_states(country_id, code, name) values (:country_id, :code, :name)',
                    [
                    'country_id'=>$country_id,
                    'code'=>$state_code,
                    'name'=>$state_name
                    ]
                );
            }
        }
    }
