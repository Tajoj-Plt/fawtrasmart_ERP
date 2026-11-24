# AGENTS.md

This file provides guidance to Codex (Codex.ai/code) when working with code in this repository.

## Common commands

- `composer dev` — everyday dev loop: `php artisan serve` + `queue:listen --tries=1` + `pail` (logs) + `npm run dev` (Vite) concurrently via `npx concurrently`.
- `composer setup` — first-time bootstrap: `composer install`, copy `.env`, `key:generate`, `migrate --force`, `npm install`, `npm run build`.
- `composer test` — `config:clear` then `php artisan test`.
- `php artisan test --filter <ClassOrMethod>` / `php artisan test tests/Feature/SprintTest.php` — single test / file.
- `vendor/bin/pint` — code style (Laravel Pint).
- `php artisan migrate` / `php artisan migrate:fresh --seed`.
- `php artisan module:list` / `module:make <Name>` / `module:enable <Name>` / `module:disable <Name>`.
- `php artisan cron:run-due --dry-run` — list scheduled jobs currently due without running them; `--job=<key>` forces one.
- `npm run dev` / `npm run build` — Vite standalone.

## Architecture

### Modular monolith
20 feature modules under `Modules/` (Account, Sales, HR, Stock, Purchases, Pos, Client, Branches, Memberships, Reports, RentalManagement, Reservations, Sitting, InsuranceAgents, PointsAndBlances, Subscriptions, TaskManager, OnlineStore, Ecosystem, UserGuide) via `nwidart/laravel-modules`. Each owns `Http/`, `Providers/`, `routes/`, `database/`, `resources/`, `config/`, PSR-4 as `Modules\<Name>\…`. A module's migrations run alongside `database/migrations/` (467 root migrations; ~276 models in `app/Models`).

Largest modules by code volume: HR, Sales, Stock, Client, OnlineStore. **`Modules/Sitting` is the system-settings hub** — appearance, languages, currencies, SMTP/SMS, API keys, backups, document templates, sequence numbering, cron jobs, activity/error/event logs, subscription. Most "where is this setting managed?" questions end there.

`modules_statuses.json` contains an `"Api": true` key with **no matching `Modules/Api` directory** — harmless, but don't chase it.

### Two independent enable/disable systems (read before debugging access issues)
1. **`modules_statuses.json`** — the nwidart, code-level switch. `false` silences that module's service provider, routes, and migrations. Full reset: edit the file → `composer dump-autoload` → `php artisan optimize:clear`. Skipping the dump-autoload is the usual cause of "class not found" after re-enabling.
2. **`application_settings` table + `App\Services\Settings\ApplicationRegistry`** — the runtime, admin-UI switch ("شاشة التطبيقات"). `ApplicationRegistry::groups()` is the *single source of truth* for the ~33 toggleable application keys, their labels, and their **route prefixes**; both the controller and the Blade cards derive from it. `App\Http\Middleware\EnsureModuleEnabled` (alias `module`, and appended to the whole `web` group in `bootstrap/app.php`) maps a request path to a module key via those prefixes and redirects to `dashboard` with an error when that module is inactive. A path matching no known prefix passes through untouched. When adding a module here, add its route prefixes to `groups()`, or put `module:<key>` explicitly on its route group.

So a page that 403s or bounces to the dashboard may be blocked by permissions, by `modules_statuses.json`, or by `application_settings` — check all three.

### Dual-kernel quirk
Laravel 11 and Laravel 10 conventions coexist. `bootstrap/app.php` is the live entry point — middleware aliases, global stack, web/api groups, routing. But `app/Http/Kernel.php` and `app/Console/Kernel.php` from the L10 layout still exist, and `bootstrap/app.php` uses reflection on `App\Console\Kernel::schedule()` to keep the legacy schedule wired. Edit `bootstrap/app.php` for middleware; touch `app/Console/Kernel.php` only for the two things still scheduled in code.

### Scheduling: DB-driven, not code-driven
`app/Console/Kernel.php::schedule()` registers essentially **one** entry — `cron:run-due` every minute — plus dynamic target-settlement jobs derived from the `target_settings` table. Everything else lives in the `cron_jobs` table (seeded by `CronJobsSeeder`, managed from the Sitting UI).

To add a scheduled task: write the Artisan command, then **register it in `App\Services\Cron\CronRegistry::commands()`** — an explicit allowlist of command names plus permitted option names; anything not listed is refused before it reaches `Artisan::call` — then add/enable a `cron_jobs` row. Do **not** add `$schedule->command(...)` to `Console\Kernel`. `CronMonitoringService::recordHeartbeat()` fires on every `cron:run-due` tick even when nothing is due; that heartbeat is the only evidence `schedule:run` is alive on the server. Supporting code lives in `app/Services/Cron/` (`CronJobRunner`, `CronJobService`, `CronScheduleParser`, `CronMonitoringService`). See `docs/CRON_SETUP_GUIDE.md`.

### Routing
`routes/web.php` holds real routes plus `require routes/auth.php`. The splitting happens in `app/Providers/RouteServiceProvider.php`, which loads ~17 topic files under the `web` middleware. Two traps:
- The provider has **duplicate `Route::middleware('web')->group(...)` lines** for several files (orders, customers, inventory, dashboard, time-tracking). Leave them unless explicitly cleaning up. Nearly every topic file also `require`s `auth.php`, so auth routes get re-declared many times over.
- Several route files exist but are **never loaded**: `checks.php`, `memberships.php`, `sms.php`, `task.php`, `customers-refactored.php`. Adding a route to one of those does nothing — confirm the file appears in `RouteServiceProvider::boot()` first.

`RouteServiceProvider::HOME` is `/dashboard/sales/index` (post-login landing).

### Permissions (Spatie)
Global helpers `hasPermission($p)`, `checkPermission($p)`, `canAny([…])`, `canAll([…])` in `app/Helpers/PermissionHelper.php`. In Blade use the custom `@permission('name') … @endpermission` directive registered in `AppServiceProvider`, not Laravel's `@can`. `@haspermission('name')` echoes the literal string `'true'`/`'false'` for JS interpolation. `php artisan permissions:sync` (`SyncPermissions`) and `GeneratePermissionMiddleware` handle permission upkeep; `app/Constants/permissions/PermissionSources.php` catalogs the sources.

### Global view state (watch out)
`AppServiceProvider::boot()` registers **three** `View::composer('*', …)` callbacks that run on every view render:
- `App\Support\LanguageSettings::apply()` → shares `$currentLanguage`, `$languageDirection`, `$languageIsRtl`, `$languageDateFormat`, `$languageTimeFormat`, `$languageCss` (cached and memoized per request).
- `Visit::with(['employee','client'])->whereDate('visit_date', today())->get()` → `$todayVisits`. **Not cached — a real query on every render.**
- `App\Support\ThemeSettings` → `$backgroundColorr`, `$systemTheme` (cached).

`boot()` also applies DB-stored SMTP settings via `App\Services\MailerConfigurator` (cached ~10 min) — outgoing mail does **not** come from `.env` alone. Avoid adding more `View::composer('*')` calls.

### Localization
Arabic (`ar`, RTL) is primary; `mcamara/laravel-localization` with `hideDefaultLocaleInURL => false`. Supported locales are **not** just `config/laravellocalization.php`: `AppServiceProvider::restrictSupportedLocalesToActiveLanguages()` overrides that config from the `system_languages` table at boot (active rows only, cached 1h under `system_languages_supported_locales`), guarded to no-op if the result is empty or drops the fallback locale. Enabling a language in the admin screen therefore changes every language switcher without touching templates. Language files live under `resources/lang/{ar,en,bn,hi,ger,ur,ja}/`; `Modules/Sitting/Services/LanguageService.php` plus `app/Support/TranslationFileParser.php` power the in-app translation editor.

Cache keys to clear when things look stale: `system_languages_supported_locales`, `active_system_languages`, `application_settings.map`.

Arabic code comments and UI strings throughout PHP/Blade are expected and intentional.

### Frontend
Vite entry is `resources/css/app.css` + `resources/js/app.js` with `@tailwindcss/vite` — Tailwind v4, no `tailwind.config.js`. React 19 and TinyMCE are in `dependencies`, but `vite.config.js` installs no React plugin — treat React as opt-in per page, not the default stack.

### Deployment layout
The repo root doubles as the web docroot: a root `index.php` bootstraps Laravel directly, and `.htaccess` rewrites file-ish requests into `public/`. That's a shared-hosting arrangement — don't assume `public/` is the document root when reasoning about asset URLs or adding rewrite rules.

### Testing
PHPUnit 11, two suites registered in `phpunit.xml`: `tests/Unit` and `tests/Feature`. `phpunit.xml` forces `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `QUEUE_CONNECTION=sync`, `CACHE_STORE=array`, `SESSION_DRIVER=array`, so tests never touch the dev DB (MySQL, per `.env`). Note that `tests/SalesReports/` is **not** in either testsuite — those files don't run under `php artisan test` unless targeted by path.

## Conventions worth knowing

- Four helper files are globally autoloaded via composer `files`: `TaskSettingsHelper.php`, `PermissionHelper.php`, `DocumentNumberHelper.php` (`next_document_number()`, `peek_document_number()`, …), `BranchSettingsHelper.php` (`current_branch_id()`, `branch_setting()`, …). The rest of `app/Helpers/` (CurrencyHelper, NotificationHelper, NumberToWords, TimezoneHelper, WeekHelper, ClientSettingsHelper) are class-based — `use` them explicitly.
- Cross-cutting business logic lives in `app/Services/` (Accounts, Invoices, Backup, Cron, Settings, Ecosystem, plus points/targets/payroll/document-template services), not in controllers.
- `app/Enums/` (~40 enums) and `app/Constants/` hold the shared vocabulary — check there before introducing new string literals for statuses, types, or system account IDs.
- The `Modules/<Name>/Providers/<Name>ServiceProvider.php` pattern boots translations/config/views from the module dir and `loadMigrationsFrom(module_path($name,'database/migrations'))`.
- Points/loyalty is a substantial subsystem spanning `Modules/PointsAndBlances`, `Modules/Ecosystem`, and `app/Services/PointsService.php`, with its own written docs in `docs/` (Points_System_Structure, Points_Transaction_Workflow, PointsService_Usage_Guide, Achievement_Action_Map, Referral_System_Guide).
