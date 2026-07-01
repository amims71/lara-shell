# lara-shell

**An interactive artisan command shell for Laravel.** Run `php artisan shell` and you get a REPL where bare words execute as artisan commands, `;`-prefixed lines evaluate as PHP (Tinker-style), and you get fuzzy command matching, managed background jobs, aliases, macros, and a production safety guard on top.

```text
$ php artisan shell
artisan> serve
  INFO  Server running on [http://127.0.0.1:8000].
artisan> route:list
artisan> migrate
```

It fuses two workflows into one prompt: the ergonomics of an artisan command runner and the power of a Tinker/PsySH PHP REPL.

---

## Requirements

- PHP **8.2+**
- Laravel **10, 11, or 12** (`illuminate/console`, `illuminate/contracts`, `illuminate/support` `^10|^11|^12`)
- `psy/psysh` `^0.12.20`, `symfony/process` `^6.4|^7`

## Installation

```bash
composer require amims71/lara-shell
```

The service provider (`Amims71\LaraShell\LaraShellServiceProvider`) is **auto-discovered** — no manual registration needed.

To customize behavior, optionally publish the config file:

```bash
php artisan vendor:publish --tag=lara-shell-config
```

This copies `config/lara-shell.php` into your app's `config/` directory.

## Quick start

Launch the shell:

```bash
php artisan shell
```

The command is also registered under the aliases `terminal` and `repl`, so `php artisan terminal` and `php artisan repl` open the same shell.

Then just type commands at the `artisan>` prompt:

```text
artisan> serve
artisan> route:list
artisan> migrate
```

New here? Type **`help`** (or `h`) for a built-in guide to everything the shell can do, and **`help <command>`** for a specific command's usage. A short hint is printed on launch.

---

## Features

### `help` and `h` — the built-in guide

Type `help` (or its aliases `h`, `about`, `guide`) for an overview of everything the shell does. Pass a command name to see its usage, arguments, and options — rendered from the live command catalog, no subprocess:

```text
artisan> help
artisan> help migrate      # usage, arguments and options for migrate
```

The shell prints a one-line hint on launch pointing you at `help` and `palette`.

### Bare-word artisan commands

Type any artisan command name (with its usual arguments and options) with no `artisan` prefix. It runs against your application:

```text
artisan> make:model Post -m
artisan> queue:work --queue=high
artisan> config:cache
```

### Fuzzy matching, `palette`, and `?`

You don't have to type the full command name. lara-shell resolves input in tiers:

1. Exact name or alias.
2. Unambiguous prefix abbreviation — `mig` → `migrate` (when only one command starts with it).
3. Colon-segment abbreviation — `m:f` → `migrate:fresh`, `r:l` → `route:list`.
4. fzf-style subsequence match, when there is a single clear winner.

```text
artisan> r:l          # resolves to route:list
artisan> m:f          # resolves to migrate:fresh
```

When you want to browse or search the catalog, use the `palette` command (aliased `?`):

```text
artisan> palette
+-------------------+------------------------------------------+
| Command           | Description                              |
+-------------------+------------------------------------------+
| ...               | ...                                      |

artisan> palette migrate
artisan> ? route
```

With no query, `palette` lists commands name-sorted; with a query it ranks by fuzzy score against command names and descriptions.

### PHP eval / Tinker fusion — the `;` escape

Anything that isn't a known command runs as PHP, exactly like Tinker (lara-shell is built on PsySH). You can also **force** a line to be treated as PHP by prefixing it with `;` (the `php_escape` character). This is useful when your PHP expression would otherwise collide with a command name:

```text
artisan> User::count()
= 42

artisan> ; migrate('a string variable, not the command')
```

An empty line and any line starting with `;` are always classified as PHP.

### Background jobs — `&`, `jobs`, `kill`, `logs`

Append `&` to any artisan command to run it detached in the background:

```text
artisan> queue:work &
Started background job a1b2c3d4 (queue:work)
```

Commands listed under `long_running` in the config (`serve`, `queue:work`, `horizon`, `pail`, `octane:start`, …) are backgrounded **automatically**, so you don't need the trailing `&`:

```text
artisan> serve
Started background job 9f8e7d6c (serve)
```

Manage running jobs with the meta-commands:

```text
artisan> jobs
+----------+------------+---------+--------+
| ID       | Command    | Status  | Uptime |
+----------+------------+---------+--------+
| a1b2c3d4 | queue:work | running | 3m12s  |
+----------+------------+---------+--------+

artisan> logs a1b2c3d4          # print the job's captured output
artisan> logs a1b2c3d4 --follow # or: logs a1b2c3d4 -f  (tail live)

artisan> kill a1b2c3d4          # terminate the job and its process tree
Killed job a1b2c3d4.
```

Jobs are tracked in a shared file registry, so `jobs`, `logs`, and `kill` see jobs started from any lara-shell session in the same project.

### Aliases and `@macro`s

**Aliases** rewrite the first word of a line. Manage them from inside the shell:

```text
artisan> alias add fresh migrate:fresh --seed
Added alias fresh => migrate:fresh --seed

artisan> fresh          # runs: migrate:fresh --seed

artisan> alias list
fresh => migrate:fresh --seed

artisan> alias rm fresh
Removed alias fresh
```

A real command always wins over an alias of the same name, and alias expansion is loop-guarded.

**Macros** are named sequences of steps, invoked with the `@` sigil (`macro_sigil`). Each step is a line (an artisan command, an alias, or another `@macro`). Macros are defined in the per-project `.lara-shell.php` file (see [Configuration](#configuration)):

```text
artisan> @deploy          # runs each step of the "deploy" macro in order
```

Macro recursion is loop-guarded and depth-capped.

Both aliases and macros are stored in a per-project **`.lara-shell.php`** file at your application root.

### Production safety guard

When your app is in a guarded environment (by default `production`), destructive commands are gated. The guard has three levels:

- **allow** — runs normally.
- **confirm** — you must **re-type the exact command name** to proceed; anything else aborts.
- **block** — refused outright.

The default config marks a list of destructive commands as **confirm** (`migrate`, `migrate:fresh`, `db:wipe`, `db:seed`, `cache:clear`, `queue:flush`, …). Passing `--force`/`-f` also escalates a command to **confirm** in a guarded environment. Outside guarded environments, everything is allowed.

```text
artisan> migrate:fresh
This command is guarded. Re-type "migrate:fresh" to proceed:
migrate:fresh
```

### `reload`

Refresh the command catalog (and, on drivers that support it, reload code):

```text
artisan> reload
Reloaded. Command catalog refreshed.
```

The current cross-platform driver already runs each command in a fresh subprocess, so your code is always current; `reload` refreshes the in-shell command catalog so newly registered commands become resolvable.

---

## Configuration

### `config/lara-shell.php`

Publish it with `php artisan vendor:publish --tag=lara-shell-config`. Keys:

| Key | Description |
| --- | --- |
| `command.name` | The artisan command name (default `shell`). |
| `command.aliases` | Command aliases (default `terminal`, `repl`). |
| `long_running` | Commands auto-backgrounded without a trailing `&`. Supports `fnmatch` patterns like `queue:*`. |
| `guard.environments` | Environments where the safety guard is active (default `['production']`). |
| `guard.block` | Commands refused outright in a guarded environment. |
| `guard.confirm` | Commands requiring a re-typed confirmation in a guarded environment. |
| `php_escape` | Prefix that forces a line to evaluate as PHP (default `;`). |
| `macro_sigil` | Prefix that invokes a macro (default `@`). |

Default config:

```php
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
            'migrate', 'migrate:fresh', 'migrate:refresh', 'migrate:reset',
            'migrate:rollback', 'db:wipe', 'db:seed', 'key:generate',
            'cache:clear', 'config:clear', 'route:clear', 'view:clear',
            'optimize:clear', 'queue:flush', 'queue:forget', 'model:prune',
            'telescope:clear', 'horizon:clear',
        ],
    ],

    'php_escape' => ';',
    'macro_sigil' => '@',
];
```

### Per-project `.lara-shell.php`

Aliases and macros live in a `.lara-shell.php` file at your application root. `alias add`/`alias rm` write to it automatically; you can also edit it by hand. It returns an array with `aliases` and `macros` keys:

```php
<?php

return [
    // First-word rewrites: name => expansion
    'aliases' => [
        'fresh' => 'migrate:fresh --seed',
        'up'    => 'migrate',
    ],

    // Named step sequences invoked with @name. Each step is a line:
    // an artisan command, an alias, or another @macro.
    'macros' => [
        'deploy' => [
            'migrate --force',
            'config:cache',
            'route:cache',
            'queue:restart',
        ],
        'reset' => [
            '@deploy',            // macros may call other macros
            'db:seed',
        ],
    ],
];
```

---

## How commands run (and what's coming)

The current cross-platform driver runs **each command as a fresh subprocess** of your app's PHP binary and `artisan` entry point. Foreground commands stream their output live; background commands are fully detached and log to `storage/lara-shell/logs/<job-id>.log`, with job metadata tracked in a shared registry so multiple terminals see the same jobs.

> **Coming in a future release:** a fast **persistent daemon** for macOS/Linux that keeps the framework booted for **instant execution**, shares jobs across terminals over a socket, and runs commands under a **PTY**. Until then, the cross-platform subprocess driver described above is the default everywhere.

---

## Testing

```bash
composer install
vendor/bin/pest
```

## License

MIT.
