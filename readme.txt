=== Releaso – Changelogs & Release Notes ===
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

Releaso shows your plugins' release notes as an elegant timeline with product tabs, type filters (New, Improved, Fixed, Removed, Security, or your own types), search, "show older" and links to every version.

**Sources**

* **WordPress.org plugin**: the readme.txt from the plugin's SVN (trunk or stable tag).
* **Installed plugin**: readme.txt, changelog.txt or CHANGELOG.md of a plugin on this site. Ideal for Pro plugins.
* **GitHub repository**: GitHub Releases, or a changelog file in the repo. Private repositories work with a token.
* **Remote URL**: any readme.txt, CHANGELOG.md or JSON file, with an optional Authorization header.
* **Paste or upload a file**: paste a changelog or load a .txt, .md or .json file.
* **Releases written here only**: write each release in the admin.

Releases you write in the admin join any source and replace its notes for the same version. Drafts stay hidden and scheduled releases appear on their date.

Rename or recolour the change types, or add your own (such as "Breaking" or "Deprecated") with the keywords that mark them, under Releaso → Settings.

**Show it**

* The **Changelog** block, with a live preview and sidebar settings. Its Color panel colours each part separately (accent, product name, version, date, release title, change text, badges, cards, borders) and every change type.
* The `[releaso]` shortcode:

`[releaso products="slider-blocks,slider-blocks-pro" layout="timeline" per_page="6" limit="0" types="new,fixed" filters="yes" search="yes" header="yes" expanded="no"]`

Colours work in the shortcode too: `accent_color`, `product_color`, `version_color`, `date_color`, `title_color`, `text_color`, `latest_color`, `tag_color`, `card_color`, `border_color`, and `type_colors="new:#0ea5e9, fixed:#e11d48"`.

**Built for production**

* Pages never wait on the network: remote sources sync in the background (WP-Cron) with ETag / Last-Modified, and a failed sync keeps the last good copy.
* Every release is in the HTML, so the changelog reads without JavaScript and is indexed by search engines.
* REST API (`releaso/v1`), WP-CLI (`wp releaso`), import / export (JSON, Markdown, readme.txt), theme-overridable templates, and filters and actions throughout.

== Installation ==

1. Install Releaso from Plugins → Add New, or upload the `releaso` folder to `/wp-content/plugins/`.
2. Activate it.
3. Go to Releaso → Add product, choose where the changelog comes from and save.
4. Add the Changelog block to a page, or paste the product's shortcode.

== Frequently Asked Questions ==

= Which changelog formats are understood? =

readme.txt (`= 1.2.0 =` headings), Markdown ("Keep a Changelog", conventional-changelog, GitHub release notes) and JSON. Change types come from prefixes ("Fixed:", "Added (Pro):", "feat:"), sections ("### Fixed") or the wording.

= How do I restyle it? =

Set CSS custom properties, e.g. `.releaso { --releaso-accent: #0a7; }`, choose an accent colour in Releaso → Settings, or copy a template from `templates/` to `yourtheme/releaso/`.

= Where do I put a GitHub token? =

In Releaso → Settings, or (preferred) `define( 'RELEASO_GITHUB_TOKEN', '…' );` in wp-config.php.

= Where is the source code of the block? =

The editor script in `build/` is compiled from `src/`, which ships with the plugin. To rebuild it, run `npm install` and `npm run build` in the plugin folder.

== External services ==

Releaso only contacts a service when you choose it as a product's source. Requests are made by your server in the background (WP-Cron), when you save a product or press "Sync now", or from WP-CLI and the REST API. Visitors' browsers never contact these services, and no visitor data is sent. Each request carries a user agent with the Releaso version and your site's address.

**WordPress.org** (source "WordPress.org plugin"). Releaso downloads the plugin's readme.txt from the plugin directory's SVN (`plugins.svn.wordpress.org`). When you choose the stable version instead of trunk, it first asks the WordPress.org Plugins API (`api.wordpress.org/plugins/info/1.2/`) which version is current. Only the plugin slug you entered is sent. [Terms of use](https://wordpress.org/about/domains/), [privacy policy](https://wordpress.org/about/privacy/).

**GitHub** (source "GitHub repository"). Releaso reads the repository's releases or a changelog file through the GitHub REST API (`api.github.com`). The repository name you entered is sent, plus your GitHub token if you added one (for private repositories and a higher rate limit). [Terms of service](https://docs.github.com/en/site-policy/github-terms/github-terms-of-service), [privacy statement](https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement).

**A URL you enter** (source "Remote URL"). Releaso downloads the changelog file from the address you enter, sending the optional Authorization header you set. That server's own terms and privacy policy apply.

The "Installed plugin", "Paste or upload a file" and "Releases written here only" sources make no external requests.

== Changelog ==

= 1.0.0 =
* Added: First release.
