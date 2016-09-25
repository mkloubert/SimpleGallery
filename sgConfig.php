<?php

/* config */

defined("SG_INDEX") or die();

return [
    'class' => "\\MyGallery",

    'context' => [
        'class' => "\\MyExecutionContext",
    ],

    'fileProvider' => [
        'class' => "\\MyFileProvider",
    ],

    'features' => [
        'allowFolders' => true,
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
