import fs from 'node:fs/promises';
import path from 'node:path';
import { execFile } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { promisify } from 'node:util';

const rootDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const sourceRoot = path.join(rootDir, 'node_modules', 'admin-lte');
const tabulatorRoot = path.join(rootDir, 'node_modules', 'tabulator-tables');
const targetRoot = path.join(rootDir, 'public', 'assets', 'vendor', 'adminlte');
const files = [
  'dist/css/adminlte.min.css',
  'dist/js/adminlte.min.js',
  'LICENSE',
];
const tabulatorFiles = [
  ['dist/css/tabulator_bootstrap5.min.css', 'dist/css/tabulator_bootstrap5.min.css'],
  ['dist/js/tabulator.min.js', 'dist/js/tabulator.min.js'],
  ['LICENSE', 'LICENSE'],
];
const checkOnly = process.argv.includes('--check');
const execFileAsync = promisify(execFile);

async function exists(file) {
  try {
    await fs.access(file);
    return true;
  } catch {
    return false;
  }
}

async function listFiles(directory) {
  if (!(await exists(directory))) return [];
  const result = [];
  async function walk(current) {
    const stat = await fs.stat(current);
    if (stat.isDirectory()) {
      for (const entry of await fs.readdir(current)) await walk(path.join(current, entry));
    } else if (stat.isFile()) {
      result.push(path.relative(directory, current).split(path.sep).join('/'));
    }
  }
  await walk(directory);
  return result.sort();
}

async function assertDependency() {
  if (!(await exists(sourceRoot))) {
    throw new Error('Missing admin-lte. Run npm ci before building frontend assets.');
  }
  if (!(await exists(tabulatorRoot))) {
    throw new Error('Missing tabulator-tables. Run npm ci before building frontend assets.');
  }
}

async function assertUntracked() {
  const { stdout } = await execFileAsync('git', ['ls-files', '--', 'public/assets/vendor/adminlte'], {
    cwd: rootDir,
    encoding: 'utf8',
  });
  const tracked = stdout.trim().split('\n').filter(Boolean);
  if (tracked.length) {
    throw new Error(`Generated AdminLTE assets must not be tracked:\n${tracked.map(file => `  ${file}`).join('\n')}`);
  }
}

async function build() {
  await fs.rm(targetRoot, { recursive: true, force: true });
  for (const file of files) {
    const target = path.join(targetRoot, file);
    await fs.mkdir(path.dirname(target), { recursive: true });
    await fs.copyFile(path.join(sourceRoot, file), target);
  }
  for (const [sourceFile, targetFile] of tabulatorFiles) {
    const target = path.join(targetRoot, 'tabulator', targetFile);
    await fs.mkdir(path.dirname(target), { recursive: true });
    await fs.copyFile(path.join(tabulatorRoot, sourceFile), target);
  }
}

async function check() {
  await assertUntracked();
  const actual = await listFiles(targetRoot);
  const expected = [...files, ...tabulatorFiles.map(([, targetFile]) => `tabulator/${targetFile}`)].sort();
  const problems = [];
  for (const file of expected) {
    const tabulatorPrefix = 'tabulator/';
    const source = file.startsWith(tabulatorPrefix)
      ? path.join(tabulatorRoot, file.slice(tabulatorPrefix.length))
      : path.join(sourceRoot, file);
    const target = path.join(targetRoot, file);
    if (!(await exists(target))) {
      problems.push(`missing: ${file}`);
    } else if (!(await fs.readFile(source)).equals(await fs.readFile(target))) {
      problems.push(`stale: ${file}`);
    }
  }
  for (const file of actual) {
    if (!expected.includes(file)) problems.push(`extra: ${file}`);
  }
  if (problems.length) throw new Error(`AdminLTE assets are out of date:\n${problems.map(item => `  ${item}`).join('\n')}`);
}

await assertDependency();
if (checkOnly) await check();
else await build();
