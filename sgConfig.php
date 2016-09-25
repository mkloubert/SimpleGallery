<?php

/* config */

defined("SG_INDEX") or die();

return [
    'context' => [
        'class' => "\\MyExecutionContext",
    ],

    'features' => [
        'allowFolders' => true,
    ],

    'fileProvider' => [
        'class' => "\\MyFileProvider",
    ],

    'filters' => [
        /* 'files' => function($path, $ctx) {
            return false;
        }, */

        /* 'folders' => function($path, $ctx) {
            return false;
        }, */
    ],

    'gallery' => [
        'class' => "\\MyGallery",
    ],

    'users' => [
        ['name' => 'user1',
         'password' => 'pwd1'],

        ['name' => 'user2',
         'password' => 'pwd2'],
    ],

    'styles' => [
        '
/* your 1st style here */
',

        '
/* your 2nd style here */
',
    ],

    'scripts' => [
        '
// your first script here
        ',

        '
// your 2nd script here
        '
    ]
];
