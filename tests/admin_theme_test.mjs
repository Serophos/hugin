import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const source = fs.readFileSync(new URL('../public/assets/js/admin-theme.js', import.meta.url), 'utf8');

function boot({ stored = null, dark = false } = {}) {
  const listeners = {};
  const mediaListeners = [];
  const attributes = {};
  const storage = new Map(stored ? [['lte-theme', stored]] : []);
  const buttons = ['light', 'dark', 'auto'].map(value => ({
    dataset: { adminThemeValue: value, bsThemeValue: value },
    getAttribute(name) { return name === 'data-bs-theme-value' ? value : null; },
    classList: { toggle() {} },
    setAttribute() {},
    addEventListener(type, callback) { listeners[`button:${value}:${type}`] = callback; },
  }));
  const document = {
    documentElement: {
      dataset: {},
      style: {},
      setAttribute(name, value) { attributes[name] = value; },
    },
    querySelectorAll(selector) { return ['[data-admin-theme-value]', '[data-bs-theme-value]'].includes(selector) ? buttons : []; },
    querySelector() { return null; },
    addEventListener(type, callback) { listeners[`document:${type}`] = callback; },
    dispatchEvent() {},
  };
  const media = {
    matches: dark,
    addEventListener(type, callback) { if (type === 'change') mediaListeners.push(callback); },
  };
  const window = {
    localStorage: {
      getItem(key) { return storage.get(key) ?? null; },
      setItem(key, value) { storage.set(key, value); },
      removeItem(key) { storage.delete(key); },
    },
    matchMedia() { return media; },
  };
  vm.runInNewContext(source, {
    window,
    document,
    CustomEvent: class { constructor(type, init) { this.type = type; this.detail = init.detail; } },
    Set,
  });
  listeners['document:DOMContentLoaded']?.();
  return { window, document, attributes, storage, media, mediaListeners, listeners };
}

{
  const app = boot({ dark: true });
  assert.equal(app.attributes['data-bs-theme'], 'dark');
  assert.equal(app.document.documentElement.dataset.adminColorMode, 'auto');
}
{
  const app = boot({ stored: 'light', dark: true });
  assert.equal(app.attributes['data-bs-theme'], 'light');
  app.window.HuginAdminTheme.apply('dark', true);
  assert.equal(app.attributes['data-bs-theme'], 'dark');
  assert.equal(app.storage.get('lte-theme'), 'dark');
}
{
  const app = boot({ stored: 'auto', dark: false });
  app.media.matches = true;
  app.mediaListeners[0]();
  assert.equal(app.attributes['data-bs-theme'], 'dark');
}
{
  const app = boot({ stored: 'invalid', dark: false });
  assert.equal(app.document.documentElement.dataset.adminColorMode, 'auto');
  assert.equal(app.window.HuginAdminTheme.resolvedTheme('light'), 'light');
}

console.log('PASS admin color theme selection and persistence');
