<?php

return [

    'search' => [
        'case_insensitive' => true,
        'use_wildcards' => false,
    ],

    'fractal' => [
        'serializer' => 'League\Fractal\Serializer\DataArraySerializer',
    ],

    'script_template' => '(function(window,$){window.LaravelDataTables=window.LaravelDataTables||{};window.LaravelDataTables["%s"]=$("#%s").DataTable(%s);})(window,jQuery);'
];
