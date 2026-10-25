<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platforms
    |--------------------------------------------------------------------------
    |
    | The platforms offered, in this order, when a share names none. Any key of
    | resources/platforms.php (php artisan share:platforms lists them) or of
    | "custom" below. A platform is skipped for a share that lacks what it
    | needs (Pinterest without an image, Facebook without a link…).
    |
    */

    'platforms' => ['facebook', 'x', 'whatsapp', 'linkedin', 'telegram', 'threads', 'bluesky', 'reddit', 'pinterest', 'email', 'copy', 'native'],

    // Named sets: Share::for($post)->preset('compact'), <x-share::buttons preset="compact" />.
    'presets' => [
        'compact' => ['facebook', 'x', 'whatsapp', 'copy'],
        'messaging' => ['whatsapp', 'telegram', 'messenger', 'viber', 'sms', 'email', 'copy'],
        'professional' => ['linkedin', 'x', 'teams', 'email', 'copy'],
    ],

    // Platforms of your own, or changes to built-in ones (merged key by key).
    // 'custom' => [
    //     'mastodon' => ['instance' => 'mas.to'],
    //     'intranet' => ['label' => 'Intranet', 'url' => 'https://intra.example/share', 'query' => ['u' => '{url}'], 'color' => '#111'],
    // ],
    'custom' => [],

    /*
    |--------------------------------------------------------------------------
    | Defaults for every share
    |--------------------------------------------------------------------------
    */

    // Account credited on X ("via @handle") and in twitter:site, without the @.
    'via' => env('SHARE_VIA'),

    'site_name' => env('SHARE_SITE_NAME', env('APP_NAME')),

    // Most hashtags added to a post (0 for none).
    'hashtags' => 2,

    // A description longer than this is cut at a word boundary.
    'text_limit' => 200,

    /*
    |--------------------------------------------------------------------------
    | Campaign tags
    |--------------------------------------------------------------------------
    |
    | Added to the shared address, so analytics can tell which platform readers
    | arrive from. {platform} is the platform key, {medium} its medium. A link
    | that already has a parameter keeps its own value.
    |
    */

    'utm' => [
        'enabled' => (bool) env('SHARE_UTM', true),
        'parameters' => [
            'utm_source' => '{platform}',
            'utm_medium' => '{medium}',
            'utm_campaign' => 'share',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Counting shares
    |--------------------------------------------------------------------------
    |
    | Web platforms go through GET /share/{platform} (a signed link: counted,
    | then redirected); the rest report to POST /share/beacon. Prefetches,
    | link-preview bots and repeats from the same reader within "dedupe"
    | minutes are not counted. Every counted share fires
    | Atannex\Share\Events\Shared.
    |
    | recorder: "database" (the share_counts table, one row per address,
    | platform and day), "null", or a class implementing
    | Atannex\Share\Contracts\Recorder. Share::recordUsing() overrides it.
    |
    */

    'tracking' => [
        'enabled' => (bool) env('SHARE_TRACKING', true),
        'recorder' => env('SHARE_RECORDER', 'database'),
        'dedupe' => 30,
        'cache' => null,
    ],

    'routes' => [
        'enabled' => true,
        'prefix' => 'share',
        'domain' => null,
        'middleware' => ['web'],
        'throttle' => '60,1',
    ],

    // Run the package's migration (share_counts). Turn off when another recorder counts.
    'migrations' => true,

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    */

    // Largest file, in MB, the script downloads to hand to the device's share sheet.
    'file_share_max_mb' => 60,

    // Desktop popup size for web platforms without their own.
    'popup' => [620, 680],

];
