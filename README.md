# DKAN Base theme

Framework-free base theme for DKAN 4 sites running `dkan_catalog_ui`. Plain
CSS with custom properties, no build step, no design-system dependency.
Generated from `starterkit_theme` (Drupal 11.4.5) and trimmed.

## Install

```bash
drush theme:enable dkan_base_theme
drush config:set system.theme default dkan_base_theme
```

Requires Drupal ^10.4 || ^11 and depends on the `dkan_catalog_ui` module
(catalog pages, icon component). Claro stays the admin theme.

## Block placement

Shipped in `config/optional`, applied when the theme is installed: site
branding (header), main menu (primary_menu), account menu (secondary_menu),
breadcrumbs, status messages (highlighted), page title, local tasks, local
actions and main content (content), footer menu (footer_menu). Admin and
tools menus are not placed; Claro's toolbar covers editors.

Changing placement later needs `drush theme:uninstall dkan_base_theme` and a
reinstall, or manual block configuration.

The branding block shows the logo only: `logo.svg` is the DKAN wordmark
(from the DKAN docs), so the site name is off. Sites with a symbol logo can
turn the name back on in the block settings or upload their own logo at
`/admin/appearance/settings/dkan_base_theme`.

`sidebar_first` renders only when it has content. `/search` draws its own
facet sidebar inside the content region, so do not place blocks in
`sidebar_first` on catalog paths.

## Header

`templates/layout/page.html.twig` renders, in this order at every width:
branding (`page.header`), the menu toggle (JS only, `data-dbt-menu-toggle`,
controls `#dbt-header-panel`), the panel with the primary and account menus
(`.dbt-header__primary`, `.dbt-header__secondary`), then the search form.
From 64em the panel is `display: contents`, giving one row: wordmark |
primary | account | search. Below that the panel opens as a sheet over the
search row; Escape and outside clicks close it (`js/menu-toggle.js`). The
account menu template (`menu--account.html.twig`) and the toggle use the
module's `dkan_catalog_ui:icon` component, which is why the theme depends
on `dkan_catalog_ui`.

The footer always renders: the site logo linking home (`dbt_logo`), the
footer menu, "Powered by DKAN" and the footer region. The skip link sits
centred above the header when focused.

Main-menu links: `dkan_catalog_ui` ships Datasets and API (`/api/docs`)
as static links; About and any other pages are site content links.

## Settings

`catalog_search_path` (default `/search`): the header search form submits a
GET request here with the term in `fulltext`, matching the catalog view's
exposed filter. Empty hides the form. Edit at
`/admin/appearance/settings/dkan_base_theme`.

## Libraries

- `global`: tokens, base, layout and component CSS on every page, plus the
  menu toggle script (`js/menu-toggle.js`, depends on `core/once`).
- `catalog`: `css/catalog.css` in the `theme` category, attached only to the
  `dkan_catalog_ui_search` view and full dataset nodes, where it overrides the
  module's component CSS.

## Styled selector contract

`css/catalog.css` styles markup the theme does not own. Changes to these
class names upstream need matching changes here:

- `dkan_catalog_ui`: `.dcu-search*` (`__filter-link` jumps to
  `#dcu-facets` under 64em; the sidebar follows the results in the DOM
  and the module's grid puts it first at 64em and up),
  `.dcu-facets*` (`__facet--long` on lists over eight items), `.dcu-card*`,
  `.dcu-dataset-header*`, `.dcu-tabs*`, `.dcu-resource*`,
  `.dcu-metadata-table*`, `.dcu-data-dictionary*`, `.dcu-endpoint*`,
  `.dcu-button`, `.dcu-icon` (inline SVG, sized by `font-size`).
- `dkan_catalog_ui_preview`: `.dcu-table*` (three toolbar rows: `__file`
  with `__chooser` or `__caption` / `__file-label` then `__download`,
  `__tools` ending in Full Screen, `__status` with `__summary` and the
  chips; `__panel` /
  `__panel-body` popovers that become bottom sheets under 48em,
  `__panel-head` / `__panel-heading` / `__panel-close` (JS-only, shown
  under 48em), `__panel-chevron`, `__link-button`, `__button-label`,
  `__column-list--long`, `__chip`, `__caption`, `__cell--number`,
  `__wrapper` scrolling horizontally with a sticky header in
  `--fullscreen` only, `--density-*`) and the core pager class names it
  reuses (centred) plus `.pager__label` (hidden under 48em).
- `facets`: `.facet-item`, `.facet-item__value`, `.facet-item__count`.
- Core: Views exposed form (`.form--inline`, `.form-item-fulltext`), pager
  (`.pager__*`), local tasks (`ul.tabs`), status messages (`.messages*`).

## Extending

### Subtheme

```yaml
name: My Site
type: theme
base theme: dkan_base_theme
core_version_requirement: ^10.4 || ^11
libraries:
  - my_site/global
regions:
  # Copy the regions from dkan_base_theme.info.yml.
```

The base theme's `global` library still loads. Override templates by copying
them into the subtheme's `templates/` directory.

### Tokens

All colors, type, spacing and sizing are `--dbt-*` custom properties on
`:root` in `css/tokens.css`. The same file maps the module's `--dcu-*`
tokens onto them. A subtheme re-skins by redefining `--dbt-*` in its own
stylesheet; run `php scripts/contrast.php` after changing colors.

```css
:root {
  --dbt-color-primary: #005ea2;
  --dbt-color-link: #005ea2;
  --dbt-font-family: "Public Sans", system-ui, sans-serif;
}
```

### Fonts

Add a library with the `@font-face` rules (or a Google Fonts stylesheet),
list it in the subtheme's `libraries`, and set `--dbt-font-family`.

### Replacing a module component

A subtheme component can take over a `dkan_catalog_ui` component with
`replaces`. Core enforces schema compatibility: the replacement must accept
the original's `props` with the same required keys and types (see the
original's `*.component.yml`). Example replacing the dataset card:

```yaml
# my_site/components/dataset-card/dataset-card.component.yml
name: Dataset card
replaces: 'dkan_catalog_ui:dataset-card'
props:
  # Copy the props block from
  # dkan_catalog_ui/components/dataset-card/dataset-card.component.yml.
```

Then `drush cr`. The replacement's Twig, CSS and JS are used everywhere the
module renders `dkan_catalog_ui:dataset-card`.

## Tests and checks

```bash
DRUPAL_ROOT=/var/www/html/docroot SIMPLETEST_BASE_URL=$DDEV_PRIMARY_URL \
  SIMPLETEST_DB=mysql://db:db@db:3306/db \
  vendor/bin/phpunit -c docroot/themes/custom/dkan_base_theme
../../../../vendor/bin/phpcs --standard=phpcs.xml.dist .
php scripts/contrast.php
```

Kernel tests cover block placement on install, the header search setting,
and catalog library attachment. The menu toggle and responsive layout are
checked manually in a browser at 375px and 1440px.
