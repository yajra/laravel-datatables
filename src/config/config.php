<?php

return [

    'search' => [
        'case_insensitive' => true,
        'use_wildcards'    => false,
    ],

    'order' => [
        //first %s column, second %s order direction
        'nulls_last_sql' => '%s %s NULLS LAST',
    ]

];
