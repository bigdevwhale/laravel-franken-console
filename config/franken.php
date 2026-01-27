<?php

declare(strict_types=1);

return [
    'polling_interval' => env('FRANKEN_POLLING_INTERVAL', 2), // seconds
    'log_levels' => ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'],
    'theme' => [
        'name' => env('FRANKEN_THEME', 'dark'),
        'colors' => [
            'primary' => 'cyan',
            'secondary' => 'yellow',
            'error' => 'red',
            'success' => 'green',
            'warning' => 'yellow',
            'info' => 'blue',
            'muted' => 'gray',
            'background' => 'black',
            'foreground' => 'white',
        ],
        'themes' => [
            'dark' => [
                'primary' => 'cyan',
                'secondary' => 'yellow',
                'error' => 'red',
                'success' => 'green',
                'warning' => 'yellow',
                'info' => 'blue',
                'muted' => 'gray',
                'background' => 'black',
                'foreground' => 'white',
            ],
            'light' => [
                'primary' => 'blue',
                'secondary' => 'magenta',
                'error' => 'red',
                'success' => 'green',
                'warning' => 'yellow',
                'info' => 'cyan',
                'muted' => 'gray',
                'background' => 'white',
                'foreground' => 'black',
            ],
        ],
    ],
    'keybindings' => [
        'quit' => 'q',
        'refresh' => 'r',
        'restart_worker' => 'R',
        'clear_cache' => 'c',
        'search_logs' => '/',
        'navigate_down' => 'j',
        'navigate_up' => 'k',
        'enter' => 'enter',
        'switch_overview' => '1',
        'switch_queues' => '2',
        'switch_jobs' => '3',
        'switch_logs' => '4',
        'switch_cache' => '5',
        'switch_scheduler' => '6',
        'switch_metrics' => '7',
        'switch_shell' => '8',
        'switch_settings' => '9',
        'page_up' => 'PageUp',
        'page_down' => 'PageDown',
        'scroll_top' => 'Home',
        'scroll_bottom' => 'End',
    ],
];