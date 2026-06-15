const fs = require('node:fs/promises');
const os = require('node:os');
const path = require('node:path');
const { execFile } = require('node:child_process');
const { promisify } = require('node:util');

const execFileAsync = promisify(execFile);

const MODULE_NAME = 'wipop';
const PACKAGED_MODULE_PATHS = [
  'autoload.php',
  'assets',
  'composer.json',
  'config',
  'config.xml',
  'controllers',
  'index.php',
  'LICENSE.txt',
  'logo.png',
  'sql',
  'src',
  'translations',
  'vendor',
  'views',
  'wipop.php',
];
const DEVELOPMENT_DIRECTORIES = new Set([
  '.git',
  '.github',
  '.idea',
  'node_modules',
  'test',
  'tests',
  'tools',
  'var',
  'vendor-dev',
]);
const DEVELOPMENT_FILES = new Set([
  '.editorconfig',
  '.gitignore',
  '.php-cs-fixer.cache',
  '.php-cs-fixer.dist.php',
  '.php-cs-fixer.php',
  '.phpunit.result.cache',
  'grumphp.yml',
  'makefile',
  'phpcs.xml',
  'phpstan-bootstrap.php',
  'phpstan.neon',
  'phpstan.neon.dist',
  'phpunit.xml',
  'phpunit.xml.dist',
]);

async function updateFile(filePath, pattern, replacement, description) {
  const content = await fs.readFile(filePath, 'utf8');

  if (!pattern.test(content)) {
    throw new Error(`Unable to update ${description} in ${filePath}`);
  }

  await fs.writeFile(filePath, content.replace(pattern, replacement));
}

async function updateModuleVersion(cwd, version) {
  const moduleDir = path.join(cwd, MODULE_NAME);

  await updateFile(
    path.join(moduleDir, 'wipop.php'),
    /(\$this->version\s*=\s*')[^']+(';)/,
    `$1${version}$2`,
    'module PHP version'
  );

  await updateFile(
    path.join(moduleDir, 'config.xml'),
    /(<version><!\[CDATA\[)[^\]]+(\]\]><\/version>)/,
    `$1${version}$2`,
    'module XML version'
  );
}

async function copyRequired(source, destination) {
  try {
    await fs.cp(source, destination, { recursive: true });
  } catch (error) {
    if (error.code === 'ENOENT') {
      throw new Error(`Missing required release path: ${source}`);
    }

    throw error;
  }
}

async function stageModule(cwd, stageRoot) {
  const sourceModule = path.join(cwd, MODULE_NAME);
  const stagedModule = path.join(stageRoot, MODULE_NAME);

  await fs.mkdir(stagedModule, { recursive: true });

  for (const modulePath of PACKAGED_MODULE_PATHS) {
    await copyRequired(path.join(sourceModule, modulePath), path.join(stagedModule, modulePath));
  }

  await copyRequired(path.join(cwd, 'README.md'), path.join(stagedModule, 'README.md'));
  await removeDevelopmentArtifacts(stagedModule);
}

async function removeDevelopmentArtifacts(directory) {
  const entries = await fs.readdir(directory, { withFileTypes: true });

  for (const entry of entries) {
    const entryPath = path.join(directory, entry.name);
    const artifactName = entry.name.toLowerCase();

    if (entry.isDirectory()) {
      if (DEVELOPMENT_DIRECTORIES.has(artifactName)) {
        await fs.rm(entryPath, { recursive: true, force: true });
        continue;
      }

      await removeDevelopmentArtifacts(entryPath);
      continue;
    }

    if (entry.isFile() && DEVELOPMENT_FILES.has(artifactName)) {
      await fs.rm(entryPath, { force: true });
    }
  }
}

async function buildReleaseZip(cwd) {
  const stageRoot = await fs.mkdtemp(path.join(os.tmpdir(), 'wipop-prestashop-release-'));
  const zipPath = path.join(cwd, 'wipop.zip');

  try {
    await stageModule(cwd, stageRoot);
    await fs.rm(zipPath, { force: true });
    await execFileAsync('zip', ['-rq', zipPath, MODULE_NAME], { cwd: stageRoot });
  } finally {
    await fs.rm(stageRoot, { recursive: true, force: true });
  }
}

module.exports = {
  prepare: async (_pluginConfig, context) => {
    const cwd = context.cwd || process.cwd();
    const version = context.nextRelease?.version;

    if (!version) {
      throw new Error('hook did not receive nextRelease.version');
    }

    context.logger.log(`Syncing PrestaShop module metadata to release version ${version}`);
    await updateModuleVersion(cwd, version);

    context.logger.log('Building PrestaShop module zip from runtime allowlist');
    await buildReleaseZip(cwd);
  },
};
