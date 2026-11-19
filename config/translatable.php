<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default translation strategy
    |--------------------------------------------------------------------------
    |
    | "table", "json" or "columns", for every translatable model that does not
    | implement TableTranslatable, JsonTranslatable or ColumnsTranslatable.
    | Changing it never moves stored data: use translatable:migrate.
    |
    | Locales come from Laravel: app.locale is the current locale and
    | app.fallback_locale the fallback of displayed values.
    |
    */

    'strategy' => env('TRANSLATABLE_STRATEGY', 'table'),

];
