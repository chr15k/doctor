<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Diagnostic Selection
    |--------------------------------------------------------------------------
    |
    | These selectors use the same syntax as the --only and --except command
    | options: diagnostic class names, groups, Composer packages, or package
    | wildcards. Leave them empty to run every registered diagnostic.
    |
    */

    'only' => [
        //
    ],

    'except' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment Modes
    |--------------------------------------------------------------------------
    |
    | Doctor maps Laravel environment names to one of its supported modes so
    | diagnostics know which expectations apply. Any environment that is
    | not listed here is treated as production, the strictest mode.
    |
    */

    'environments' => [
        'local' => 'local',
        'production' => 'production',
        // 'staging' => 'production', // Laravel environment => Doctor mode
    ],

];
