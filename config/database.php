<?php

return [

    'default' => env('DB_CONNECTION', 'mysql'),

	'connections' => [
		'mysql' => [
            'driver'    => 'mysql',
            'host'      => env('DB_HOST'),
            'port'      => env('DB_PORT'),
            'database'  => env('DB_DATABASE'),
            'username'  => env('DB_USERNAME'),
            'password'  => env('DB_PASSWORD'),
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
         ],

        'expand' => [
            'driver'    => 'mysql',
            'host'      => env('DB_SUGAR_HOST'),
            'port'      => env('DB_SUGAR_PORT'),
            'database'  => env('DB_SUGAR_DATABASE'),
            'username'  => env('DB_SUGAR_USERNAME'),
            'password'  => env('DB_SUGAR_PASSWORD'),
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
        ],
	],
];