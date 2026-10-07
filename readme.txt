=== Releaso – Plugin Changelogs ===
Contributors: gutenbergkits
Tags: changelog, release notes, readme, github releases, block
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Beautiful, filterable changelogs for your plugins, from WordPress.org, GitHub, readme.txt, CHANGELOG.md, JSON or releases you write.

== Description ==

Releaso shows your plugins' release notes as an elegant timeline with product tabs, type filters (New, Improved, Fixed, Removed, Security), search, "show older" and links to every version.

**Sources**

* **WordPress.org plugin**: the readme.txt from the plugin's SVN (trunk or stable tag).
* **Installed plugin**: readme.txt, changelog.txt or CHANGELOG.md of a plugin on this site. Ideal for Pro plugins.
* **GitHub repository**: GitHub Releases, or a changelog file in the repo. Private repositories work with a token.
* **Remote URL**: any readme.txt, CHANGELOG.md or JSON file, with an optional Authorization header.
* **Paste or upload a file**: paste a changelog or load a .txt, .md or .json file.
* **Releases written here only**: write each release in the admin.

Releases you write in the admin join any source and replace its notes for the same version. Drafts stay hidden and scheduled releases appear on their date.

**Show it**

* The **Changelog** block, with a live preview and sidebar settings. Its Color panel colours each part separately (accent, product name, version, date, release title, change text, badges, cards, borders) and every change type.
* The `[releaso]` shortcode:

`[releaso products="slider-blocks,slider-blocks-pro" layout="timeline" per_page="6" limit="0" types="new,fixed" filters="yes" search="yes" header="yes" expanded="no"]`

Colours work in the shortcode too: `accent_color`, `product_color`, `version_color`, `date_color`, `title_color`, `text_color`, `latest_color`, `tag_color`, `card_color`, `border_color`, and `type_colors="new:#0ea5e9, fixed:#e11d48"`.

**Built for production**

* Pages never wait on the network: remote sources sync in the background (WP-Cron) with ETag / Last-Modified, and a failed sync keeps the last good copy.
* Every release is in the HTML, so the changelog reads without JavaScript and is indexed by search engines.
* REST API (`releaso/v1`), WP-CLI (`wp releaso`), import / export (JSON, Markdown, readme.txt), theme-overridable templates, and filters and actions throughout.

== Frequently Asked Questions ==

= Which changelog formats are understood? =

readme.txt (`= 1.2.0 =` headings), Markdown ("Keep a Changelog", conventional-changelog, GitHub release notes) and JSON. Change types come from prefixes ("Fixed:", "Added (Pro):", "feat:"), sections ("### Fixed") or the wording.

= How do I restyle it? =

Set CSS custom properties, e.g. `.releaso { --releaso-accent: #0a7; }`, choose an accent colour in Releaso → Settings, or copy a template from `templates/` to `yourtheme/releaso/`.

= Where do I put a GitHub token? =

In Releaso → Settings, or (preferred) `define( 'RELEASO_GITHUB_TOKEN', '…' );` in wp-config.php.

== Changelog ==

= 1.0.0 =
* Added: First release.
