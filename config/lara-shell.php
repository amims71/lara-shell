<?php

return [
    'command' => ['name' => 'shell', 'aliases' => ['terminal', 'repl']],

    'long_running' => [
        'serve',
        'queue:work',
        'queue:listen',
        'schedule:work',
        'horizon',
        'horizon:work',
        'reverb:start',
        'pail',
        'octane:start',
        'websockets:serve',
    ],

    'guard' => [
        'environments' => ['production'],
        'block' => [],
        'confirm' => [
            'migrate',
            'migrate:fresh',
            'migrate:refresh',
            'migrate:reset',
            'migrate:rollback',
            'db:wipe',
            'db:seed',
            'key:generate',
            'cache:clear',
            'config:clear',
            'route:clear',
            'view:clear',
            'optimize:clear',
            'queue:flush',
            'queue:forget',
            'model:prune',
            'telescope:clear',
            'horizon:clear',
        ],
    ],

    'php_escape' => ';',
    'macro_sigil' => '@',
];
