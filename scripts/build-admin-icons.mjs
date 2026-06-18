import { promises as fs } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const React = require('react');
const { renderToStaticMarkup } = require('react-dom/server');

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const rootDir = path.resolve(__dirname, '..');
const iconDir = path.join(rootDir, 'public/assets/icons/admin');
const checkOnly = process.argv.includes('--check');

const icons = [
  ['add', 'action/Plus'],
  ['back', 'direction/ArowBack'],
  ['cancel', 'application/Close'],
  ['delete', 'action/Trash'],
  ['edit', 'action/Edit'],
  ['login', 'legacy/SignIn'],
  ['logout', 'legacy/SignOut'],
  ['manage', 'action/Gear'],
  ['menu', 'application/Menu'],
  ['move', 'action/Dragable'],
  ['open', 'legacy/ExternalLink'],
  ['preview', 'status/Visible'],
  ['reload', 'action/Reload'],
  ['remove', 'action/Minus'],
  ['save', 'action/Save'],
  ['settings', 'action/Setting'],
  ['upload', 'action/FileUpload']
];

function renderIcon(source) {
  const module = require(`@rsuite/icon-font/lib/${source}`);
  const Icon = module.default || module;
  const markup = renderToStaticMarkup(
    React.createElement(Icon, {
      'aria-hidden': 'true',
      focusable: 'false',
      xmlns: 'http://www.w3.org/2000/svg'
    })
  );

  return [
    '<!-- Generated from @rsuite/icon-font by scripts/build-admin-icons.mjs. Do not edit directly. -->',
    markup,
    ''
  ].join('\n');
}

async function readText(file) {
  try {
    return await fs.readFile(file, 'utf8');
  } catch (error) {
    if (error && error.code === 'ENOENT') {
      return null;
    }
    throw error;
  }
}

async function main() {
  const stale = [];

  if (!checkOnly) {
    await fs.mkdir(iconDir, { recursive: true });
  }

  for (const [name, source] of icons) {
    const target = path.join(iconDir, `${name}.svg`);
    const next = renderIcon(source);

    if (checkOnly) {
      const current = await readText(target);
      if (current !== next) {
        stale.push(`${path.relative(rootDir, target)} (${source})`);
      }
      continue;
    }

    await fs.writeFile(target, next, 'utf8');
  }

  if (stale.length > 0) {
    console.error('Generated admin icons are out of date:');
    for (const file of stale) {
      console.error(`- ${file}`);
    }
    console.error('Run `npm run build:icons` and commit the generated SVGs.');
    process.exitCode = 1;
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
