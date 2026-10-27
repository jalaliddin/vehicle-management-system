<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Units per fuel type
    |--------------------------------------------------------------------------
    */

    'units' => [
        'benzin' => 'L',
        'dizel' => 'L',
        'gaz' => 'm³',
    ],

    /*
    |--------------------------------------------------------------------------
    | Overconsumption tolerance
    |--------------------------------------------------------------------------
    |
    | A vehicle is flagged when its estimated consumption exceeds its norm by
    | more than this many percent.
    |
    */

    'overconsumption_percent' => (int) env('FUEL_OVERCONSUMPTION_PERCENT', 10),

];
