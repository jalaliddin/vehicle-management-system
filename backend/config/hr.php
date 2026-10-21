<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Warning threshold
    |--------------------------------------------------------------------------
    |
    | A medical exam, briefing, PPE item or driving license is "due soon" when
    | its deadline falls within this many days.
    |
    */

    'alert_due_soon_days' => (int) env('HR_ALERT_DUE_SOON_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Annual leave
    |--------------------------------------------------------------------------
    |
    | Default annual leave entitlement in calendar days for new employees.
    |
    */

    'annual_leave_days' => (int) env('HR_ANNUAL_LEAVE_DAYS', 21),

    /*
    |--------------------------------------------------------------------------
    | Dismissal reasons
    |--------------------------------------------------------------------------
    */

    'dismissal_reasons' => [
        'own_wish' => 'Xodimning tashabbusi bilan',
        'agreement' => "Tomonlarning kelishuviga ko'ra",
        'contract_end' => 'Mehnat shartnomasi muddati tugashi',
        'employer_initiative' => 'Ish beruvchining tashabbusi bilan',
        'retirement' => 'Pensiyaga chiqish',
        'other' => 'Boshqa',
    ],

    /*
    |--------------------------------------------------------------------------
    | Backups
    |--------------------------------------------------------------------------
    |
    | Where `php artisan app:backup` writes database and file archives, and how
    | many it keeps: every backup of the last `keep_daily` days plus the first
    | backup of each of the last `keep_monthly` months.
    |
    */

    'backup' => [
        'path' => env('BACKUP_PATH', storage_path('backups')),
        'keep_daily' => (int) env('BACKUP_KEEP_DAILY', 30),
        'keep_monthly' => (int) env('BACKUP_KEEP_MONTHLY', 12),
    ],

];
