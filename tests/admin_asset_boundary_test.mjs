import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');

const packageJson = JSON.parse(read('package.json'));
assert.equal(packageJson.devDependencies['admin-lte'], '4.1.0', 'AdminLTE must remain exactly pinned');
assert.equal(packageJson.devDependencies.bootstrap, '5.3.8', 'Bootstrap must remain exactly pinned');
assert.equal(packageJson.devDependencies['tabulator-tables'], '6.3.1', 'AdminLTE data tables must use the pinned Tabulator package');

const adminHeader = read('app/Views/layouts/admin_header.php');
const adminUserMenu = read('app/Views/admin/partials/user_menu.php');
assert.match(adminHeader, /assets\/vendor\/adminlte\/dist\/css\/adminlte\.min\.css/);
assert.match(adminHeader, /assets\/vendor\/adminlte\/tabulator\/dist\/css\/tabulator_bootstrap5\.min\.css/);
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

const adminFooter = read('app/Views/layouts/admin_footer.php');
assert.match(adminFooter, /assets\/vendor\/adminlte\/dist\/js\/adminlte\.min\.js/);
assert.match(adminFooter, /assets\/vendor\/adminlte\/bootstrap\/dist\/js\/bootstrap\.bundle\.min\.js/);
assert.match(adminFooter, /assets\/vendor\/adminlte\/tabulator\/dist\/js\/tabulator\.min\.js/);

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

for (const file of [
  'app/Views/admin/playlists.php',
  'app/Views/admin/slides.php',
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

const adminCss = read('public/assets/css/admin.css');
const themeCss = read('public/assets/css/admin-theme-overrides.css');
assert.match(themeCss, /\[data-bs-theme="dark"\]/, 'dark mode must have a scoped compatibility layer');
assert.match(themeCss, /--panel:\s*var\(--bs-body-bg\)/, 'legacy layout tokens must resolve through Bootstrap theme variables');
assert.match(
  adminCss,
  /td\.actions\s*>\s*\.admin-action-group\s*\{[\s\S]*?justify-content:\s*flex-end;[\s\S]*?margin:\s*0\.15rem 0 0\.15rem auto;/,
  'admin action button groups must be explicitly right-aligned',
);
assert.match(
  read('public/assets/js/admin-table.js'),
  /isActionColumn[\s\S]*?admin-actions-column/,
  'Tabulator conversion must preserve an explicit action-column marker',
);
assert.match(
  adminCss,
  /\.tabulator-cell\.admin-actions-column\s*>\s*\.admin-action-group\s*\{[\s\S]*?margin-left:\s*auto;/,
  'Tabulator action groups must remain right-aligned after table enhancement',
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

console.log('PASS AdminLTE dependency and frontend asset boundaries');
