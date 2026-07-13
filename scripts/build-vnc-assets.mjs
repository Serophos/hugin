import fs from 'node:fs/promises';
import path from 'node:path';
import { execFile } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { promisify } from 'node:util';

const rootDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const sourceRoot = path.join(rootDir, 'node_modules', '@novnc', 'novnc');
const targetRoot = path.join(rootDir, 'public', 'assets', 'vendor', 'novnc');
const entries = ['core', 'vendor', 'AUTHORS', 'LICENSE.txt'];
const checkOnly = process.argv.includes('--check');
const execFileAsync = promisify(execFile);

async function pathExists(filePath) {
  try {
    await fs.access(filePath);
    return true;
  } catch {
    return false;
  }
}

async function listFiles(baseDir) {
  if (!(await pathExists(baseDir))) {
    return [];
  }

  const result = [];
  async function walk(current) {
    const stat = await fs.stat(current);
    if (stat.isDirectory()) {
      const children = await fs.readdir(current);
      for (const child of children) {
        await walk(path.join(current, child));
      }
      return;
    }

    if (stat.isFile()) {
      result.push(path.relative(baseDir, current).split(path.sep).join('/'));
    }
  }

  await walk(baseDir);
  return result.sort();
}

async function copyAssets() {
  if (!(await pathExists(sourceRoot))) {
    throw new Error('Missing @novnc/novnc. Run npm install first.');
  }

  await fs.rm(targetRoot, { recursive: true, force: true });
  await fs.mkdir(targetRoot, { recursive: true });

  for (const entry of entries) {
    await fs.cp(path.join(sourceRoot, entry), path.join(targetRoot, entry), { recursive: true });
  }
}

async function checkAssets() {
  await checkVendorAssetsAreUntracked();

  const missing = [];
  const stale = [];
  const expected = [];

  for (const entry of entries) {
    const sourcePath = path.join(sourceRoot, entry);
    if (!(await pathExists(sourcePath))) {
      missing.push(`source:${entry}`);
      continue;
    }

    const stat = await fs.stat(sourcePath);
    if (stat.isDirectory()) {
      const files = await listFiles(sourcePath);
      for (const file of files) {
        expected.push(`${entry}/${file}`);
      }
    } else if (stat.isFile()) {
      expected.push(entry);
    }
  }

  const actual = new Set(await listFiles(targetRoot));
  const expectedSet = new Set(expected);

  for (const file of expected) {
    const sourceFile = path.join(sourceRoot, file);
    const targetFile = path.join(targetRoot, file);
    if (!actual.has(file)) {
      missing.push(file);
      continue;
    }

    const [sourceContent, targetContent] = await Promise.all([
      fs.readFile(sourceFile),
      fs.readFile(targetFile),
    ]);
    if (!sourceContent.equals(targetContent)) {
      stale.push(file);
    }
  }

  const extra = [...actual].filter(file => !expectedSet.has(file));
  if (missing.length > 0 || stale.length > 0 || extra.length > 0) {
    console.error('noVNC assets are out of date:');
    for (const file of missing) console.error(`  missing: ${file}`);
    for (const file of stale) console.error(`  stale: ${file}`);
    for (const file of extra) console.error(`  extra: ${file}`);
    process.exit(1);
  }
}

async function checkVendorAssetsAreUntracked() {
  let stdout;
  try {
    ({ stdout } = await execFileAsync('git', ['ls-files', '--', 'public/assets/vendor'], {
      cwd: rootDir,
      encoding: 'utf8',
    }));
  } catch (error) {
    if (error?.code === 128) {
      return;
    }
    throw error;
  }

  const tracked = stdout.trim().split('\n').filter(Boolean);
  if (tracked.length === 0) {
    return;
  }

  console.error('Generated npm vendor assets must not be tracked:');
  for (const file of tracked) {
    console.error(`  tracked: ${file}`);
  }
  console.error('Run `git rm -r --cached public/assets/vendor` and keep the generated files ignored.');
  process.exit(1);
}

if (checkOnly) {
  await checkAssets();
} else {
  await copyAssets();
}
