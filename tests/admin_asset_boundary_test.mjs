import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');

const packageJson = JSON.parse(read('package.json'));
assert.equal(packageJson.devDependencies['admin-lte'], '4.1.0', 'AdminLTE must remain exactly pinned');
assert.equal(packageJson.devDependencies.bootstrap, '5.3.8', 'Bootstrap must remain exactly pinned');
assert.equal(packageJson.devDependencies['bootstrap-icons'], '1.13.1', 'Bootstrap Icons must remain exactly pinned');

const adminHeader = read('app/Views/layouts/admin_header.php');
const adminUserMenu = read('app/Views/admin/partials/user_menu.php');
assert.match(adminHeader, /assets\/vendor\/adminlte\/dist\/css\/adminlte\.min\.css/);
assert.doesNotMatch(adminHeader, /tabulator/i, 'native AdminLTE tables must not load a competing table theme');
assert.match(adminHeader, /assets\/js\/admin-theme\.js/);
assert.ok(adminHeader.indexOf('admin-theme.js') < adminHeader.indexOf('adminlte.min.css'), 'theme must be applied before CSS to prevent a color-mode flash');
assert.match(adminUserMenu, /data-admin-theme-value="light"/);
assert.match(adminUserMenu, /data-admin-theme-value="dark"/);
assert.match(adminUserMenu, /data-admin-theme-value="auto"/);
assert.match(adminUserMenu, /data-bs-theme-value="light"/);
assert.match(adminUserMenu, /data-bs-theme-value="dark"/);
assert.match(adminUserMenu, /data-bs-theme-value="auto"/);
assert.doesNotMatch(adminHeader, /<aside[^>]*(?:bg-dark|data-bs-theme="dark")/, 'admin navigation must inherit the active color mode');
assert.doesNotMatch(adminHeader, /https?:\/\/[^'"]*admin-?lte/i, 'Admin pages must use local AdminLTE assets');
assert.match(adminUserMenu, /class="nav-item dropdown admin-user-menu"/);
assert.ok(adminUserMenu.includes("url('/admin/account')"));
assert.ok(adminUserMenu.indexOf('admin-user-menu__profile') < adminUserMenu.indexOf('admin-user-menu__preferences') && adminUserMenu.indexOf('admin-user-menu__preferences') < adminUserMenu.indexOf('admin-user-menu__actions'));
assert.ok(adminUserMenu.includes("url('/admin/account/locale')"));
assert.match(adminUserMenu, /admin-user-menu__menu[\s\S]*?action="<\?= e\(url\('\/admin\/logout'\)\)/);
assert.doesNotMatch(adminHeader, /admin-nav-form|admin-sidebar-user/, 'user identity and logout must not remain in the sidebar');
assert.ok(adminHeader.includes("<span class=\"nav-icon admin-nav__icon\"><?= admin_icon($item['icon']) ?></span>"), 'sidebar icons must use the fixed-width AdminLTE nav-icon slot');

const sidebarCss = read('public/assets/css/admin.css');
assert.match(sidebarCss, /\.admin-nav__icon \.button-icon\s*\{[^}]*display:\s*block;[^}]*width:\s*1rem;[^}]*height:\s*1rem;/s, 'sidebar icon masks must use a consistent centered size');

const adminFooter = read('app/Views/layouts/admin_footer.php');
assert.match(adminFooter, /assets\/vendor\/adminlte\/dist\/js\/adminlte\.min\.js/);
assert.match(adminFooter, /assets\/vendor\/adminlte\/bootstrap\/dist\/js\/bootstrap\.bundle\.min\.js/);
assert.doesNotMatch(adminFooter, /tabulator/i, 'native AdminLTE tables must not load the Tabulator runtime');

const templateEditor = read('app/Views/admin/slide_template_form.php');
for (const hook of [
  'data-template-editor',
  'data-editor-canvas',
  'data-orientation-tab',
  'data-inspector-tab',
  'data-inspector-panel',
  'data-template-spec',
]) {
  assert.ok(templateEditor.includes(hook), `template editor must retain ${hook}`);
}
assert.match(templateEditor, /card shadow-sm template-editor/);
assert.match(templateEditor, /form-check form-switch/);
assert.match(templateEditor, /nav nav-tabs template-editor__canvas-tabs/);
assert.match(templateEditor, /nav nav-tabs template-editor__inspector-tabs/);
assert.match(templateEditor, /btn-toolbar template-editor__tools/);
assert.match(templateEditor, /btn-group template-tool-split/);
assert.match(templateEditor, /dropdown-toggle dropdown-toggle-split template-tool-dropdown-toggle/);
assert.match(templateEditor, /dropdown-menu template-tool-menu/);
assert.doesNotMatch(templateEditor, /\bbtn-app\b/, 'AdminLTE 4.1 does not provide a btn-app component');
assert.match(templateEditor, /card card-warning collapsed-card/);
assert.match(templateEditor, /data-lte-toggle="card-collapse"/);
const canvasToolbarIcons = ['textarea-t', 'card-text', 'card-image', 'qr-code', 'calendar-date', 'stopwatch', 'slash-square', 'square', 'circle', 'triangle', 'diamond', 'star', 'hexagon', 'pentagon', 'arrow-left-right', 'toggle-on', 'toggle-off'];
for (const icon of canvasToolbarIcons) {
  assert.ok(templateEditor.includes("admin_icon('" + icon + "')") || templateEditor.includes("'icon' => '" + icon + "'"), 'canvas toolbar must use Bootstrap icon ' + icon);
}
assert.doesNotMatch(templateEditor, /template-tool-(?:text|dynamic-text|media|qr|shape).png/, 'canvas toolbar must not use legacy bitmap icons');
for (const legacyIcon of ['template-tool-text.png', 'template-tool-dynamic-text.png', 'template-tool-media.png', 'template-tool-qr.png', 'template-tool-shape.png']) {
  assert.ok(!fs.existsSync(path.join(root, 'public/assets/icons/admin/' + legacyIcon)), 'legacy canvas toolbar asset must be removed: ' + legacyIcon);
}
const playlistsView = read('app/Views/admin/playlists.php');
assert.ok(playlistsView.includes("admin_icon('add')"), 'adding an existing playlist must use the add icon');
assert.ok(playlistsView.includes("admin_icon('playlists')"), 'creating a playlist must use a distinct playlist icon');
assert.ok(playlistsView.includes('data-lte-icon="expand"') && playlistsView.includes("admin_icon('chevron-down')"), 'collapsed playlist cards must show a down chevron');
assert.ok(playlistsView.includes('data-lte-icon="collapse"') && playlistsView.includes("admin_icon('chevron-up')"), 'expanded playlist cards must show an up chevron');
assert.match(playlistsView, /<button[^>]*data-playlist-card-title[^>]*>[\s\S]*?playlist-display-group__name[\s\S]*?<\/button>/, 'playlist card titles must be keyboard-accessible buttons');
assert.match(playlistsView, /\[data-playlist-card-title\][\s\S]*?data-lte-toggle="card-collapse"[\s\S]*?\.click\(\)/, 'playlist card titles must delegate to the existing card collapse control');
assert.match(playlistsView, /data-playlist-card-title[^>]*aria-expanded="false"[\s\S]*?setAttribute\("aria-expanded"/, 'playlist card titles must expose their expanded state');
assert.ok(playlistsView.includes("admin_icon('play')"), 'the currently playing playlist must use the generated Bootstrap play icon');
assert.match(playlistsView, /reported_channel_id[\s\S]*?channel_id[\s\S]*?channel\.currently_playing[\s\S]*?class="sr-only"/, 'the current-playlist marker must match the heartbeat-reported playlist and expose an accessible message');
assert.match(playlistsView, /monitoring_status[\s\S]*?last_reported_playing_on_display/, 'offline playlist markers must identify playback as a last report rather than current inferred playback');

assert.match(
  playlistsView,
  /action="<\?= e\(url\('\/admin\/displays\/' \. \$display\['id'\] \. '\/reload'\)\) \?>"[\s\S]*?csrf_field\(\)[\s\S]*?name="return_to" value="\/admin\/playlists"[\s\S]*?admin_icon\('reload'\)[\s\S]*?__\('common\.reload'\)/,
  'each assigned display must expose the existing CSRF-protected reload action and return to playlists',
);
const adminController = read('app/Controllers/AdminController.php');
assert.match(adminController, /h\.current_channel_id[\s\S]*?LEFT JOIN display_heartbeats h ON h\.display_id = d\.id/, 'the playlists query must reuse heartbeat current-playlist state');
const reloadDisplayMethod = adminController.match(/public function reloadDisplay\(int \$id\): void[\s\S]*?(?=\n    public function )/)?.[0] || '';
assert.match(reloadDisplayMethod, /\$this->auth->requireLogin\(\)/, 'display reload must be available to authenticated admins and editors');
assert.doesNotMatch(reloadDisplayMethod, /requireRole\('admin'\)/, 'display reload must not remain admin-only');

const slidesView = read('app/Views/admin/slides.php');
assert.doesNotMatch(slidesView, /slide-group|data-lte-toggle="card-collapse"/, 'slides view must not wrap its table in a collapsible card');
assert.ok(slidesView.includes('class="card shadow-sm slide-library-card"') && slidesView.includes('class="card-body"'), 'slides table must retain a title-less card for visual structure');
assert.doesNotMatch(slidesView, /class="card-header"/, 'slides card must remain title-less');
const pluginsView = read('app/Views/admin/plugins.php');
assert.ok(pluginsView.includes('toggle-off') && pluginsView.includes('toggle-on'), 'plugin disable and enable actions must use toggle-off and toggle-on icons');


for (const file of [
  'app/Views/admin/playlists.php',
  'app/Views/admin/slide_template_form.php',
  'plugins/tl1-menu/views/slide_settings.php',
]) {
  const markup = read(file);
  assert.doesNotMatch(markup, /<details\b|<summary\b/, `${file} must use AdminLTE CardWidget rather than native details`);
  assert.match(markup, /data-lte-toggle="card-collapse"/, `${file} must expose an AdminLTE card collapse control`);
}

const adminViewFiles = fs.readdirSync(path.join(root, 'app/Views/admin'))
  .filter(file => file.endsWith('.php'))
  .map(file => `app/Views/admin/${file}`);
let actionCellCount = 0;
for (const file of adminViewFiles) {
  const markup = read(file);
  const actionCells = markup.matchAll(/<td\s+class="actions"[^>]*>([\s\S]*?)<\/td>/g);
  for (const match of actionCells) {
    actionCellCount += 1;
    if (/\bbutton\b|<a\b|<form\b/.test(match[1])) {
      assert.match(match[1], /admin-action-group/, `${file} row action buttons must use the shared right-aligned button group`);
      const primaryCount = (match[1].match(/\bbtn-primary\b/g) || []).length;
      assert.equal(primaryCount, 1, `${file} actionable row must contain exactly one primary action`);
    }
  }
}
assert.ok(actionCellCount >= 11, 'admin action-cell inventory unexpectedly shrank');
assert.match(
  read('app/Views/admin/displays.php'),
  /class="btn btn-secondary"[^>]*href="<\?= e\(url\(\$displayPreviewUrl\)\)/,
  'display preview must be a secondary action',
);
assert.match(
  read('app/Views/admin/slides.php'),
  /class="btn btn-secondary"[^>]*href="<\?= e\(url\('\/preview-slide\//,
  'slide preview must be a secondary action',
);
assert.match(
  read('app/Views/admin/slides.php'),
  /class="admin-action-groups">[\s\S]*?admin-action-group[\s\S]*?preview-slide[\s\S]*?\/edit[\s\S]*?<\/div>\s*<div class="btn-group btn-group-sm admin-action-group"[\s\S]*?\/delete/,
  'slide deletion must be the final action in a separate button group',
);

for (const [view, destructivePath] of [
  ['users.php', 'delete'],
  ['slide_templates.php', 'delete'],
  ['locations.php', 'delete'],
  ['location_edit.php', 'delete'],
  ['displays.php', 'delete'],
  ['media.php', 'delete'],
  ['playlists.php', 'delete'],
  ['playlist_form.php', 'remove'],
  ['schedules.php', 'delete'],
]) {
  const source = read('app/Views/admin/' + view);
  const separatedActionPattern = new RegExp('class="admin-action-groups">[\\s\\S]*?<\\/div>\\s*(?:<\\?php[^>]*>\\s*)?<div class="btn-group btn-group-sm admin-action-group"[\\s\\S]*?\\/' + destructivePath);
  assert.match(source, separatedActionPattern, view + ' must place its destructive row action in a separate trailing button group');
}

const slideForm = read('app/Views/admin/slide_form.php');
const slideFormActions = slideForm.match(/<div class="form-actions">[\s\S]*?<\/div>\s*<\/form>/)?.[0] || "";
assert.doesNotMatch(slideFormActions, /button--(?:normal|default)|class="[^"]*\bbutton\b/, 'slide editor actions must not use the legacy button styling layer');
assert.match(slideFormActions, /btn btn-primary d-inline-flex/, 'slide editor primary action must use native Bootstrap styling');
assert.match(slideFormActions, /btn btn-outline-primary d-inline-flex/, 'slide editor secondary save action must use native Bootstrap styling');
assert.match(slideForm, /class="form-check-input" type="checkbox" name="is_active"/, 'slide active state must use a native Bootstrap checkbox');

const slideTemplates = read('app/Views/admin/slide_templates.php');
assert.match(slideTemplates, /<table[^>]*data-admin-table/, 'slide templates must use the shared native data-table behavior');
assert.equal((slideTemplates.match(/data-admin-sort=/g) || []).length, 4, 'slide templates must expose four sortable data columns');
assert.equal((slideTemplates.match(/data-admin-filter=/g) || []).length, 4, 'slide templates must expose four column filters');
assert.match(slideTemplates, /data-admin-sort="usage" data-sort-type="number"/, 'slide-template usage sorting must be numeric');
assert.ok(slideTemplates.includes('data-filter-value="<?= e($statusValue) ?>"'), 'slide-template status filtering must use stable values');

const adminCss = read('public/assets/css/admin.css');
assert.doesNotMatch(adminCss, /\.template-editor__snap-toggle span::before/, 'snap toggle must not render the removed legacy grid pseudo-icon');
assert.match(adminCss, /form-check-input:checked \+ \.form-check-label \[data-snap-icon="on"\]/, 'snap toggle icon must follow the checkbox checked state');
assert.match(adminCss, /\.template-editor__topbar\.card-header \{[\s\S]*?z-index:\s*2;[\s\S]*?overflow:\s*visible;/, 'template editor toolbar must stack above the canvas layout');
assert.match(adminCss, /\.template-editor__layout \{[\s\S]*?z-index:\s*1;/, 'template editor canvas descendants must remain in a lower stacking context');
const breadcrumbCss = adminCss.match(/\.admin-breadcrumb \{[\s\S]*?(?=\n\.topbar \{)/)?.[0] || "";
assert.doesNotMatch(breadcrumbCss, /#[0-9a-f]{3,8}\b/i, "breadcrumbs must inherit native Bootstrap color-mode colors");
assert.doesNotMatch(breadcrumbCss, /::after/, "breadcrumbs must use Bootstrap native dividers");
const themeCss = read('public/assets/css/admin-theme-overrides.css');
assert.match(themeCss, /\[data-bs-theme="dark"\]/, 'dark mode must have a scoped compatibility layer');
assert.match(themeCss, /--panel:\s*var\(--bs-body-bg\)/, 'legacy layout tokens must resolve through Bootstrap theme variables');
const adminTableJs = read('public/assets/js/admin-table.js');
assert.doesNotMatch(adminTableJs, /Tabulator|table\.remove\(\)|admin-data-table/, 'admin tables must remain native AdminLTE tables');
assert.match(adminTableJs, /classList\.add\("form-control", "form-control-sm"\)/, 'text filters must use native Bootstrap form controls');
assert.match(adminTableJs, /classList\.add\("form-select", "form-select-sm"\)/, 'select filters must use native Bootstrap selects');
assert.match(read('app/Views/admin/slides.php'), /data-admin-table-state-key="slides"/, 'slides table must restore filtering and sorting after delete redirects');
assert.match(adminTableJs, /window\.sessionStorage\.(?:getItem|setItem)/, 'opt-in admin tables must persist state for redirects and reloads');
assert.ok(adminTableJs.includes('sortColumn') && adminTableJs.includes('sortDirection') && adminTableJs.includes('filters'), 'persistent admin table state must include filters and sorting');
assert.match(
  adminCss,
  /td\.actions\s*>\s*\.admin-action-group\s*\{[\s\S]*?justify-content:\s*flex-end;[\s\S]*?margin:\s*0\.15rem 0 0\.15rem auto;/,
  'admin action button groups must be explicitly right-aligned',
);

const canvasRenderer = read('public/assets/js/template-canvas-renderer.js');
assert.doesNotMatch(canvasRenderer, /\b(?:admin-?lte|btn-app|form-control|nav-tabs)\b/i, 'canvas renderer output must stay independent from AdminLTE');

const frontendFiles = [
  'app/Views/frontend/display.php',
  'app/Controllers/FrontendController.php',
  'public/assets/css/display.css',
];
for (const file of frontendFiles) {
  assert.doesNotMatch(read(file), /admin-?lte/i, `${file} must not load or depend on AdminLTE`);
}

const gitignore = read('.gitignore');
assert.match(gitignore, /^node_modules\/$/m, 'node_modules must remain ignored');
assert.match(gitignore, /^public\/assets\/vendor\/$/m, 'generated npm vendor assets must remain ignored');

const pluginManifests = [
  ['plugins/weather/Plugin.php', 'assets/weather.css'],
  ['plugins/brightsky-dwd-weather/Plugin.php', 'assets/brightsky-dwd-weather.css'],
  ['plugins/flip-clock/Plugin.php', 'assets/flip-clock.css'],
  ['plugins/screen-meta/Plugin.php', 'assets/screen-meta.css'],
  ['plugins/tl1-menu/Plugin.php', 'assets/tl1menu.css'],
];
for (const [file, asset] of pluginManifests) {
  assert.ok(read(file).includes(asset), `${file} must retain render asset ${asset}`);
}

const tl1FrontendCss = read('plugins/tl1-menu/assets/tl1menu.css');
const tl1AdminCss = read('plugins/tl1-menu/assets/tl1menu-admin.css');
const tl1GlobalSettings = read('plugins/tl1-menu/views/global_settings.php');
const tl1SetupJs = read('plugins/tl1-menu/assets/tl1menu-setup.js');
assert.doesNotMatch(tl1AdminCss, /#[0-9a-f]{3,8}\b/i, 'TL1 admin CSS must use Bootstrap color-mode variables instead of fixed colors');
assert.match(tl1AdminCss, /\.tl1menu-global-settings > fieldset \{[\s\S]*?background:\s*var\(--bs-body-bg\);/, 'TL1 global fieldsets must use the active Bootstrap body surface');
assert.match(tl1AdminCss, /checkbox-row:has\(input:checked\)[\s\S]*?background:\s*var\(--bs-primary-bg-subtle\);/, 'TL1 checked rows must use the active Bootstrap primary surface');
assert.doesNotMatch(tl1GlobalSettings, /button--(?:normal|default)/, 'TL1 global controls must not use legacy light-only buttons');
assert.doesNotMatch(tl1SetupJs, /button--(?:normal|default)/, 'generated TL1 setup controls must not use legacy light-only buttons');
assert.match(tl1GlobalSettings, /class="form-check-input" type="checkbox"/, 'TL1 global checkboxes must use native Bootstrap controls');
assert.match(tl1GlobalSettings, /class="form-control" type="file"/, 'TL1 uploads must use native Bootstrap controls');

for (const selector of ['.tl1menu-list__stage', '.tl1menu-list__item', '.tl1menu-list__prices']) {
  assert.ok(tl1FrontendCss.includes(selector), 'TL1 frontend CSS must retain list-view selector ' + selector);
  assert.ok(!tl1AdminCss.includes(selector), 'TL1 list-view selector ' + selector + ' must not live only in the admin CSS');
}
assert.doesNotMatch(tl1FrontendCss, /--bs-[\w-]+/, 'TL1 frontend CSS must not depend on Bootstrap variables');

console.log('PASS AdminLTE dependency and frontend asset boundaries');
