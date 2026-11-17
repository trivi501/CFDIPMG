<?php

use App\Http\Middleware\RegistrarConsultaAuditoria;
use Opcodes\LogViewer\Http\Middleware\AuthorizeLogViewer;
use Opcodes\LogViewer\Http\Middleware\EnsureFrontendRequestsAreStateful;

return [

    /*
    |--------------------------------------------------------------------------
    | Log Viewer
    |--------------------------------------------------------------------------
    | Log Viewer can be disabled, so it's no longer accessible via browser.
    |
    */

    'enabled' => env('LOG_VIEWER_ENABLED', true),

    'api_only' => env('LOG_VIEWER_API_ONLY', false),

    'require_auth_in_production' => true,

    /*
    |--------------------------------------------------------------------------
    | Log Viewer Domain
    |--------------------------------------------------------------------------
    | You may change the domain where Log Viewer should be active.
    | If the domain is empty, all domains will be valid.
    |
    */

    'route_domain' => null,

    /*
    |--------------------------------------------------------------------------
    | Log Viewer Route
    |--------------------------------------------------------------------------
    | Log Viewer will be available under this URL.
    |
    */

    'route_path' => 'log-viewer',

    /*
    |--------------------------------------------------------------------------
    | Log Viewer Assets Path (Deprecated)
    |--------------------------------------------------------------------------
    | The path to the published Log Viewer assets.
    |
    | Note: Publishing assets is no longer required. Assets are now served
    | directly from the vendor directory. This option only applies if you
    | have published assets using `php artisan log-viewer:publish`.
    | This option will be removed in the next major version.
    |
    */

    'assets_path' => 'vendor/log-viewer',

    /*
    |--------------------------------------------------------------------------
    | Back to system URL
    |--------------------------------------------------------------------------
    | When set, displays a link to easily get back to this URL.
    | Set to `null` to hide this link.
    |
    | Optional label to display for the above URL.
    |
    */

    'back_to_system_url' => config('app.url', null),

    'back_to_system_label' => 'Volver al sistema',

    /*
    |--------------------------------------------------------------------------
    | Log Viewer time zone.
    |--------------------------------------------------------------------------
    | The time zone in which to display the times in the UI. Defaults to
    | the application's timezone defined in config/app.php.
    |
    */

    // La app guarda en UTC; se muestra en hora de México, igual que la bitácora de auditoría.
    'timezone' => 'America/Mexico_City',

    /*
    |--------------------------------------------------------------------------
    | Log Viewer datetime format.
    |--------------------------------------------------------------------------
    | The format used to display timestamps in the UI.
    |
    */

    'datetime_format' => 'd/m/Y H:i:s',

    /*
    |--------------------------------------------------------------------------
    | Log Viewer route middleware.
    |--------------------------------------------------------------------------
    | Optional middleware to use when loading the initial Log Viewer page.
    |
    */

    // Solo el rol Admin: lo decide el gate viewLogViewer de AppServiceProvider.
    'middleware' => [
        'web',
        'auth',
        AuthorizeLogViewer::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Viewer API middleware.
    |--------------------------------------------------------------------------
    | Optional middleware to use on every API request. The same API is also
    | used from within the Log Viewer user interface.
    |
    */

    'api_middleware' => [
        EnsureFrontendRequestsAreStateful::class,
        'auth',
        AuthorizeLogViewer::class,
        // Estas rutas no pasan por el grupo web: las descargas de logs se registran en la bitácora aquí.
        RegistrarConsultaAuditoria::class,
    ],

    // Sin definir, se usa el dominio de APP_URL: debe coincidir con el dominio real del servidor.
    'api_stateful_domains' => is_string($dominios = env('LOG_VIEWER_API_STATEFUL_DOMAINS')) && $dominios !== ''
        ? explode(',', $dominios)
        : null,

    /*
    |--------------------------------------------------------------------------
    | Log Viewer Remote hosts.
    |--------------------------------------------------------------------------
    | Log Viewer supports viewing Laravel logs from remote hosts. They must
    | be running Log Viewer as well. Below you can define the hosts you
    | would like to show in this Log Viewer instance.
    |
    */

    'hosts' => [
        'local' => [
            'name' => 'Este servidor',
        ],

        // 'staging' => [
        //     'name' => 'Staging',
        //     'host' => 'https://staging.example.com/log-viewer',
        //     'auth' => [      // Example of HTTP Basic auth
        //         'username' => 'username',
        //         'password' => 'password',
        //     ],
        //     'verify_server_certificate' => true,
        // ],
        //
        // 'production' => [
        //     'name' => 'Production',
        //     'host' => 'https://example.com/log-viewer',
        //     'auth' => [      // Example of Bearer token auth
        //         'token' => env('LOG_VIEWER_PRODUCTION_TOKEN'),
        //     ],
        //     'headers' => [
        //         'X-Foo' => 'Bar',
        //     ],
        //     'verify_server_certificate' => true,
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Include file patterns
    |--------------------------------------------------------------------------
    |
    */

    'include_files' => [
        // Solo los logs de la app (storage/logs): laravel*.log y worker.log de la cola.
        '*.log',
        '**/*.log',
    ],

    /*
    |--------------------------------------------------------------------------
    | Exclude file patterns.
    |--------------------------------------------------------------------------
    | This will take precedence over included files.
    |
    */

    'exclude_files' => [
        // 'my_secret.log'
    ],

    /*
    |--------------------------------------------------------------------------
    | Hide unknown files.
    |--------------------------------------------------------------------------
    | The include/exclude options above might catch files which are not
    | logs supported by Log Viewer. In that case, you can hide them
    | from the UI and API calls by setting this to true.
    |
    */

    'hide_unknown_files' => true,

    /*
    |--------------------------------------------------------------------------
    |  Shorter stack trace filters.
    |--------------------------------------------------------------------------
    | Lines containing any of these strings will be excluded from the full log.
    | This setting is only active when the function is enabled via the user interface.
    |
    */

    'shorter_stack_trace_excludes' => [
        '/vendor/symfony/',
        '/vendor/laravel/framework/',
        '/vendor/barryvdh/laravel-debugbar/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache driver
    |--------------------------------------------------------------------------
    | Cache driver to use for storing the log indices. Indices are used to speed up
    | log navigation. Defaults to your application's default cache driver.
    |
    */

    // En archivos (storage/framework/cache), no en la base: la caché de la app está en la base de
    // datos y el índice de logs no tiene por qué escribir ni borrar filas ahí.
    'cache_driver' => env('LOG_VIEWER_CACHE_DRIVER', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Cache key prefix
    |--------------------------------------------------------------------------
    | Log Viewer prefixes all the cache keys created with this value. If for
    | some reason you would like to change this prefix, you can do so here.
    | The format of Log Viewer cache keys is:
    | {prefix}:{version}:{rest-of-the-key}
    |
    */

    'cache_key_prefix' => 'lv',

    /*
    |--------------------------------------------------------------------------
    | Chunk size when scanning log files lazily
    |--------------------------------------------------------------------------
    | The size in MB of files to scan before updating the progress bar when searching across all files.
    |
    */

    'lazy_scan_chunk_size_in_mb' => 50,

    'strip_extracted_context' => true,

    /*
    |--------------------------------------------------------------------------
    | Per page options
    |--------------------------------------------------------------------------
    | Define the available options for number of results per page
    |
    */

    'per_page_options' => [10, 25, 50, 100, 250, 500],

    /*
    |--------------------------------------------------------------------------
    | Default settings for Log Viewer
    |--------------------------------------------------------------------------
    | These settings determine the default behaviour of Log Viewer. Many of
    | these can be persisted for the user in their browser's localStorage,
    | if the `use_local_storage` option is set to true.
    |
    */

    'defaults' => [

        // Whether to use browser's localStorage to store user preferences.
        // If true, user preferences saved in the browser will take precedence over the defaults below.
        'use_local_storage' => true,

        // Method to sort the folders. Other options: `Alphabetical`, `ModifiedTime`
        'folder_sorting_method' => 'ModifiedTime', // SortingMethod::ModifiedTime

        // Order to sort the folders. Other options: `Ascending`, `Descending`
        'folder_sorting_order' => 'desc', // SortingOrder::Descending

        // Method for sorting log-files into directories. Other options: `Alphabetical`, `ModifiedTime`
        'file_sorting_method' => 'ModifiedTime', // SortingMethod::ModifiedTime

        // Order to sort the logs. Other options: `Ascending`, `Descending`
        'log_sorting_order' => 'desc', // SortingOrder::Descending

        // Number of results per page. Must be one of the above `per_page_options` values
        'per_page' => 25,

        // Color scheme for the Log Viewer. Other options: `System`, `Light`, `Dark`
        'theme' => 'System', // Theme::System

        // Whether to enable `Shorter Stack Traces` option by default
        'shorter_stack_traces' => false,

    ],

    /*
    |--------------------------------------------------------------------------
    | Exclude IP from identifiers
    |--------------------------------------------------------------------------
    | By default, file and folder identifiers include the server's IP address
    | to ensure uniqueness. In load-balanced environments with shared storage,
    | this can cause "No results" errors. Set to true to exclude IP addresses
    | from identifier generation for consistent results across servers.
    |
    */

    'exclude_ip_from_identifiers' => env('LOG_VIEWER_EXCLUDE_IP_FROM_IDENTIFIERS', false),

    /*
    |--------------------------------------------------------------------------
    | Root folder prefix
    |--------------------------------------------------------------------------
    | The prefix for log files inside Laravel's `storage/logs` folder.
    | Log Viewer does not show the full path to these files in the UI,
    | but only the filename prefixed with this value.
    |
    */

    'root_folder_prefix' => 'root',
];
