# Franken-Console

A high-end TUI dashboard for Laravel.

## Installation

```bash
composer require --dev bigdevwhale/laravel-franken-console
```

# Franken Console 🧟‍♂️🚀

An extensible, developer-first console for Laravel-style apps. Franken Console provides a compact UI to inspect logs, queues, metrics, cache and scheduled jobs — and safely run permitted shell commands. Built with adapters and panels so you can plug in the services you already use. ✨

--

Why Franken Console?
- Lightweight, focused on developer observability
- Adapter-driven: swap implementations without changing panels
- Fast to install and easy to secure

Key highlights
- 🧩 Panels: Logs, Metrics, Queues, Jobs, Scheduler, Cache, Shell Exec, Overview, Settings
- 🛠️ Adapters: Cache, Queue, Metrics, Log — implement your own to fit infra
- 🎨 Themeable UI and configurable panel layout
- 🔒 Secureable via app middleware or IP/role restrictions

Status: Prototype / Developer Tool
License: MIT

---

Quick install

Clone or add as a dev dependency:

```bash
git clone https://github.com/bigdevwhale/laravel-franken-console
cd franken-console
composer install
```

Or require via Composer (if published):

```bash
composer require --dev franken-php/console
```

Publish configuration

```bash
php artisan vendor:publish --provider="Franken\FrankenServiceProvider" --tag="franken-config"
```

Then update `config/franken.php` to register adapters, enable panels, and set theme.

Basic usage

Start the console UI (artisan command provided by the package):

```bash
php artisan franken
```

Or mount the UI routes in your app and open the dashboard in a browser.

Configuration (example)

```php
return [
	'theme' => 'dark',
	'panels' => [
		'overview' => true,
		'logs' => true,
		'metrics' => true,
		'queues' => true,
		'jobs' => true,
		'scheduler' => true,
		'shell' => false,
	],
	'adapters' => [
		'cache' => App\Adapters\CacheAdapter::class,
		'queue' => App\Adapters\QueueAdapter::class,
		'metrics' => App\Adapters\MetricsAdapter::class,
		'log' => App\Adapters\LogAdapter::class,
	],
];
```

Panels overview
- **Overview** — quick health indicators and counters
- **Logs** — streamed and historical logs with filters
- **Metrics** — charts and counters from registered metrics adapter
- **Queues** — inspect queue sizes and pending jobs
- **Jobs** — recent jobs and status details
- **Scheduler** — list scheduled tasks and next run times
- **Shell Exec** — execute allowed commands (use with caution)
- **Settings** — toggle panels and themes

Adapters

Adapters live in `src/Adapters`. Implement the provided interfaces to integrate custom services (cache backends, queue systems, metrics providers, log storage). Register your adapter class in `config/franken.php`.

Security

- Always protect the console behind application auth or middleware.
- Disable `Shell Exec` by default and whitelist allowed commands.
- Use role-based access control for destructive actions.

Development

- Run tests:

```bash
composer test
```

- Coding style: follow PSR-12 and the project's conventions.

Contributing

Contributions are welcome! Please:
- Open issues for bugs or feature requests
- Fork and submit PRs against `main` with tests
- Add changelog entries for user-visible changes

Acknowledgements

Inspired by lightweight observability consoles and the Laravel developer experience.

License

MIT — see LICENSE for details.

---
