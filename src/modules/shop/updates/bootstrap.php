<?php

use Db\Helper as DbHelper;
use Db\UpdateManager;
use Db\Structure;

/**
 * Migrate Legacy Tables If Found
 */

$existingTables = DbHelper::listTables();
$tableMigrations = [
    'shop_order_notifications' => 'shop_customer_notifications',
];
$migrationsPerformed = false;

foreach ($tableMigrations as $legacyTable => $table) {
    if (in_array($legacyTable, $existingTables)) {
        $archiveTableName = '__archived__'.$legacyTable;

        //If LEGACY TABLE IS EMPTY, drop and continue
        $legacyTableRows = DbHelper::scalar('SELECT COUNT(*) FROM `'.$legacyTable.'`');
        if (!$legacyTableRows) {
            try {
                DbHelper::query('DROP TABLE `'.$legacyTable.'`');
            } catch (\Exception $e) {
                //ignore
            }
            continue;
        }

        //DROP ALREADY CREATED TABLE IF EXISTS
        $newTableExists = in_array($table, $existingTables);
        if ($newTableExists) {
            try {
                DbHelper::query('DROP TABLE `' . $table . '`');
                $newTableExists = false;
            } catch (\Exception $e) {
                //ignore
            }
        }

        //RECREATE TABLE FROM LEGACY (inherit custom fields)
        if (!$newTableExists) {
            try {
                DbHelper::query('CREATE TABLE `' . $table . '` LIKE `' . $legacyTable . '` ');
                $migrationsPerformed = true;
                $newTableExists = true;
            } catch (\Exception $e) {
                //ignore
            }
        }

        //COPY DATA IF NEW TABLE IS EMPTY
        $newTableRows = DbHelper::scalar('SELECT COUNT(*) FROM `'.$table.'`');
        if ($newTableExists && !$newTableRows) {
            try {
                DbHelper::query('INSERT INTO `'.$table.'` SELECT * FROM `'.$legacyTable.'`');
                DbHelper::query('RENAME TABLE `'.$legacyTable.'` TO `'.$archiveTableName.'`');
            } catch (\Exception $e) {
                //ignore
            }
        }
    }
}

if ($migrationsPerformed) {
    UpdateManager::applyDbStructure(PATH_APP, 'users');
    Structure::saveAll();
}



/**
 * Populate Countries and States
 */

$countries_and_states = [
    'US' => [
        'United States',
        [
            'Alabama' => 'AL',
            'Alaska' => 'AK',
            'American Samoa' => 'AS',
            'Arizona' => 'AZ',
            'Arkansas' => 'AR',
            'California' => 'CA',
            'Colorado' => 'CO',
            'Connecticut' => 'CT',
            'Delaware' => 'DE',
            'Dist. of Columbia' => 'DC',
            'Florida' => 'FL',
            'Georgia' => 'GA',
            'Guam' => 'GU',
            'Hawaii' => 'HI',
            'Idaho' => 'ID',
            'Illinois' => 'IL',
            'Indiana' => 'IN',
            'Iowa' => 'IA',
            'Kansas' => 'KS',
            'Kentucky' => 'KY',
            'Louisiana' => 'LA',
            'Maine' => 'ME',
            'Maryland' => 'MD',
            'Marshall Islands' => 'MH',
            'Massachusetts' => 'MA',
            'Michigan' => 'MI',
            'Micronesia' => 'FM',
            'Minnesota' => 'MN',
            'Mississippi' => 'MS',
            'Missouri' => 'MO',
            'Montana' => 'MT',
            'Nebraska' => 'NE',
            'Nevada' => 'NV',
            'New Hampshire' => 'NH',
            'New Jersey' => 'NJ',
            'New Mexico' => 'NM',
            'New York' => 'NY',
            'North Carolina' => 'NC',
            'North Dakota' => 'ND',
            'Northern Marianas' => 'MP',
            'Ohio' => 'OH',
            'Oklahoma' => 'OK',
            'Oregon' => 'OR',
            'Palau' => 'PW',
            'Pennsylvania' => 'PA',
            'Puerto Rico' => 'PR',
            'Rhode Island' => 'RI',
            'South Carolina' => 'SC',
            'South Dakota' => 'SD',
            'Tennessee' => 'TN',
            'Texas' => 'TX',
            'Utah' => 'UT',
            'Vermont' => 'VT',
            'Virginia' => 'VA',
            'Virgin Islands' => 'VI',
            'Washington' => 'WA',
            'West Virginia' => 'WV',
            'Wisconsin' => 'WI',
            'Wyoming' => 'WY'
        ]
    ],
    'CA' => [
        'Canada',
        [
            'Alberta' => 'AB',
            'British Columbia' => 'BC',
            'Manitoba' => 'MB',
            'New Brunswick' => 'NB',
            'Newfoundland and Labrador' => 'NL',
            'Northwest Territories' => 'NT',
            'Nova Scotia' => 'NS',
            'Nunavut' => 'NU',
            'Ontario' => 'ON',
            'Prince Edward Island' => 'PE',
            'Quebec' => 'QC',
            'Saskatchewan' => 'SK',
            'Yukon' => 'YT'
        ]
    ],

];

foreach ($countries_and_states as $country_code => $data) {
    $country_name = $data[0];
    DbHelper::query('insert into shop_countries(code, name, enabled) values (:code, :name, 1)', [
        'code' => $country_code,
        'name' => $country_name
    ]);

    $country_id = DbHelper::driver()->get_last_insert_id();

    foreach ($data[1] as $state_name => $state_code) {
        DbHelper::query('insert into shop_states(country_id, code, name) values (:country_id, :code, :name)', [
            'country_id' => $country_id,
            'code' => $state_code,
            'name' => $state_name
        ]);
    }
}

DbHelper::query('alter table shop_countries change column code_iso_numeric code_iso_numeric varchar(10)');

$countries_and_states = [
    [
        'name' => 'Australia',
        'code_3' => 'AUS',
        'code_iso_numeric' => '036',
        'code' => 'AU',
        'states' => [
            'AU-NSW' => 'New South Wales',
            'AU-QLD' => 'Queensland',
            'AU-SA' => 'South Australia',
            'AU-TAS' => 'Tasmania',
            'AU-VIC' => 'Victoria',
            'AU-WA' => 'Western Australia'
        ]
    ]
];

foreach ($countries_and_states as $info) {
    DbHelper::query(
        'insert into shop_countries(code, name, code_3, code_iso_numeric, enabled) 
              values (:code, :name, :code_3, :code_iso_numeric, 1)',
        $info
    );

    $country_id = DbHelper::driver()->get_last_insert_id();
    if (isset($info['states'])) {
        foreach ($info['states'] as $state_code => $state_name) {
            DbHelper::query('insert into shop_states(country_id, code, name) values (:country_id, :code, :name)',
                [
                    'country_id' => $country_id,
                    'code' => $state_code,
                    'name' => $state_name
                ]
            );
        }
    }
}
