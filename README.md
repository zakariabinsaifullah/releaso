# Releaso — developer notes

Changelogs for WordPress plugins from WordPress.org, GitHub, readme.txt, CHANGELOG.md, JSON or releases written in the admin. Shown with the `releaso/changelog` block or the `[releaso]` shortcode.

## Setup

```bash
composer install     # PHPUnit + WPCS (dev only; the plugin has its own autoloader)
npm install
npm run build        # block → build/
npm start            # watch mode
composer test        # unit tests (parsers, models; no WordPress needed)
composer lint        # WordPress Coding Standards
npm run plugin-zip   # distributable zip
```

## Architecture

```
releaso.php                 Bootstrap: constants, PHP check, autoloader, activation hooks
includes/
  Plugin.php                Container: builds services once, wires hooks
  Model/                    Change, Release, ReleaseCollection, Product (value objects)
  Parser/                   Pure PHP, unit-tested: Classifier, Readme/Markdown/Json parsers, ParserFactory (detection)
  Source/                   SourceInterface + Wporg, InstalledPlugin, Github, Url, Text, Manual; SourceRegistry
  Data/                     PostTypes, ProductRepository, ReleaseRepository, ChangelogService, Importer, Exporter
  Cron/Scheduler.php        Hourly background sync of due products
  Core/                     Installer (activation, versioned upgrades), Lifecycle (cache invalidation)
  Frontend/                 Renderer, View helpers, Assets, Shortcode, Block
  Admin/                    Product / release screens, Settings, Import / Export, field renderer
  Rest/RestController.php   releaso/v1
  Cli/Command.php           wp releaso
templates/                  changelog.php, layouts/{timeline,compact}.php, parts/changes.php (theme-overridable)
src/blocks/changelog/       Block editor source (built to build/)
assets/                     Front-end + admin CSS/JS (dependency-free, no build)
tests/                      PHPUnit unit tests + fixtures
```

### Data flow

1. A **product** (`releaso_product` post) has a source key and source settings.
2. `ChangelogService::sync()` asks the source to fetch (conditional requests / content hashes), parses the result, and stores a **snapshot** (`_releaso_snapshot`) and **state** (`_releaso_state`: checked/synced times, error, validators). Failures keep the last snapshot.
3. `ChangelogService::releases()` merges the snapshot with published **manual releases** (`releaso_release` posts, matched by version; manual wins) and caches the result in a transient. The cache is keyed by a version number that is bumped on any change, so invalidation is O(1).
4. The **Renderer** reads only cached / stored data. Rendering never makes a network request.

Sync triggers: product save, "Sync now", hourly cron (remote sources every N hours, local ones hourly; failures retried hourly), plugin updates (installed sources), REST, WP-CLI.

## Extending

| Hook | Use |
|---|---|
| `releaso_sources` | Add a source (implement `SourceInterface`, usually extend `AbstractSource`) |
| `releaso_change_types` | Add / rename change types (label, colour, alias words) |
| `releaso_layouts` | Add a layout (template at `templates/layouts/{key}.php` or in the theme) |
| `releaso_releases` | Filter a product's merged releases before display |
| `releaso_render_args`, `releaso_render` | Adjust display args / final HTML |
| `releaso_template` | Change a template path |
| `releaso_http_args`, `releaso_wporg_readme_url` | Adjust fetching |
| `releaso_format_inline` | Change how change text is formatted |
| `releaso_manage_capability` | Capability for settings / import / sync (default `manage_options`) |
| `releaso_rest_public` | Return false to require login for REST reads |
| `releaso_fetched`, `releaso_changelog_updated`, `releaso_sync_failed`, `releaso_imported`, `releaso_loaded` | Actions |

## REST API

```
GET  /wp-json/releaso/v1/products
GET  /wp-json/releaso/v1/products/{slug}/releases?limit=10&types=new,fixed
POST /wp-json/releaso/v1/products/{id}/sync              (manage)
POST /wp-json/releaso/v1/products/{id}/import            (manage) content, format, overwrite, status, dry_run
```

## WP-CLI

```
wp releaso list
wp releaso sync [<product>...] [--due]
wp releaso import CHANGELOG.md --product=<slug> [--format=] [--overwrite] [--draft] [--dry-run]
wp releaso export [<product>...] --format=json|markdown|readme
wp releaso flush
```

## JSON format

```json
{
  "releases": [
    {
      "version": "1.2.0",
      "date": "2026-09-29",
      "title": "Optional headline",
      "notes": "Optional intro, inline **Markdown**.",
      "url": "https://…",
      "changes": [ { "type": "new", "text": "Dark mode", "tag": "Pro" } ]
    }
  ]
}
```

`changes` may also be a map (`{ "added": ["…"], "fixed": ["…"] }`) or a list of strings ("Fixed: …"). GitHub's `/releases` API response is accepted as is.
