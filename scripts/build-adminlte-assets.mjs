import fs from 'node:fs/promises';
import path from 'node:path';
import { execFile } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { promisify } from 'node:util';

const rootDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const sourceRoot = path.join(rootDir, 'node_modules', 'admin-lte');
const bootstrapRoot = path.join(rootDir, 'node_modules', 'bootstrap');
const targetRoot = path.join(rootDir, 'public', 'assets', 'vendor', 'adminlte');
const files = [
  'dist/css/adminlte.min.css',
  'dist/js/adminlte.min.js',
  'LICENSE',
];
const bootstrapFiles = [
  ['dist/js/bootstrap.bundle.min.js', 'dist/js/bootstrap.bundle.min.js'],
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
  for (const [directory, name] of [
    [sourceRoot, 'admin-lte'],
    [bootstrapRoot, 'bootstrap'],
  ]) {
    if (!(await exists(directory))) throw new Error(`Missing ${name}. Run npm ci before building frontend assets.`);
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

async function copyFiles(root, prefix, mappings) {
  for (const [sourceFile, targetFile] of mappings) {
    const target = path.join(targetRoot, prefix, targetFile);
    await fs.mkdir(path.dirname(target), { recursive: true });
    await fs.copyFile(path.join(root, sourceFile), target);
  }
}

async function build() {
  await fs.rm(targetRoot, { recursive: true, force: true });
  await copyFiles(sourceRoot, '', files.map(file => [file, file]));
  await copyFiles(bootstrapRoot, 'bootstrap', bootstrapFiles);
}

async function check() {
  await assertUntracked();
  const expectedSources = new Map([
    ...files.map(file => [file, path.join(sourceRoot, file)]),
    ...bootstrapFiles.map(([source, target]) => [`bootstrap/${target}`, path.join(bootstrapRoot, source)]),
  ]);
  const actual = await listFiles(targetRoot);
  const problems = [];
  for (const [file, source] of expectedSources) {
    const target = path.join(targetRoot, file);
    if (!(await exists(target))) problems.push(`missing: ${file}`);
    else if (!(await fs.readFile(source)).equals(await fs.readFile(target))) problems.push(`stale: ${file}`);
  }
  for (const file of actual) {
    if (!expectedSources.has(file)) problems.push(`extra: ${file}`);
  }
  if (problems.length) throw new Error(`AdminLTE assets are out of date:\n${problems.map(item => `  ${item}`).join('\n')}`);
}

await assertDependency();
if (checkOnly) await check();
else await build();
