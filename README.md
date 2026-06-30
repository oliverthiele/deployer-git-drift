# deployer-git-drift — Detect direct server-side file changes before deployment

Deployer recipe that detects and warns about files modified directly on the server (via FTP, SFTP, or SSH) before they are silently overwritten by the next deployment.

[![Packagist Version](https://img.shields.io/packagist/v/oliver-thiele/deployer-git-drift.svg)](https://packagist.org/packages/oliver-thiele/deployer-git-drift)
[![PHP](https://img.shields.io/packagist/dependency-v/oliver-thiele/deployer-git-drift/php.svg)](https://php.net/)
[![License](https://img.shields.io/packagist/l/oliver-thiele/deployer-git-drift.svg)](LICENSE)
[![Changelog](https://img.shields.io/badge/Changelog-CHANGELOG.md-blue.svg)](CHANGELOG.md)

> **Early Alpha (v0.1.1-alpha2)** — This package is functional but under active development. Task names and configuration keys may change before v1.0. Use in production at your own risk.

## The Problem

Deployer uses an atomic symlink swap for zero-downtime deployments. This means there is no Git repository on the production server — only the deployed files exist. When someone modifies files directly on the server (editing a config file, quick-fixing a bug via FTP, uploading an asset), those changes are invisible. The next deployment silently overwrites them.

This recipe solves the problem by:

1. Initializing a shallow Git repository in each release directory after deployment
2. Checking for file changes in the current release before the next deployment starts
3. Warning the developer and requiring explicit confirmation before overwriting

## Requirements

- PHP >= 8.2
- Deployer >= 7.0
- Git installed on the remote server

## Installation

```bash
composer require --dev oliver-thiele/deployer-git-drift
```

## Usage

In your `deploy.php`:

```php
require 'recipe/common.php';
require __DIR__ . '/vendor/oliver-thiele/deployer-git-drift/src/GitDrift.php';

// Hook into the deployment flow
after('deploy:symlink', 'git-drift:init');
before('deploy:vendors', 'git-drift:check');
```

Hooks are opt-in by design — the recipe registers tasks only, not automatic hooks.

## Configuration

```php
// Always abort when drift is detected (default: false — ask interactively)
set('git_drift_abort_on_drift', true);

// Ignore paths that are expected to differ from the Git state
// Typical candidates: generated files, caches, uploads, installed dependencies
set('git_drift_ignore_paths', [
    'vendor/',
    'node_modules/',
    'public/typo3temp/',
    'var/',
    'public/fileadmin/',
]);
```

## Available Tasks

| Task | Description |
|---|---|
| `git-drift:init` | Initialize Git tracking in the release directory after deployment |
| `git-drift:check` | Check for drift before deployment — warns or aborts |
| `git-drift:status` | Show drift status without deploying |

Run the status check manually at any time:

```bash
dep git-drift:status production
```

## Example Output

When drift is detected:

```
⚠ Server drift detected:

 public/index.php | 5 +++--
 config/system/settings.php | 12 ++++++++----

Untracked files added on server:
public/fileadmin/direct-upload.zip

These changes were made directly on the server.
They will be LOST after this deployment.

Continue deployment and discard changes? [y/N]
```

## How it works

After each deployment, `git-drift:init` runs `git init` in the release directory, fetches the deployed branch with `--depth=1`, and sets `FETCH_HEAD` as the baseline via `git reset`. Any subsequent server-side file modifications will appear as changes relative to this baseline.

Paths listed in `git_drift_ignore_paths` are written to `.git/info/exclude` (local gitignore, does not modify project files) so generated directories are excluded from drift detection.

## License

MIT — Oliver Thiele
