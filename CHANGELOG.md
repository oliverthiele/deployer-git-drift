# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `git-drift:reset` rebuilds the baseline of the current release without a deployment, for releases where `git-drift:init` could not reach the repository and drift tracking never started. It re-fetches the deployed branch and rebuilds the baseline from it; the working tree is left untouched, so server-side changes that were already present remain visible as drift instead of being accepted

### Changed

- Documented that the automatic export-ignore detection only sees `.gitattributes` and Deployer's shared paths, so exclude mechanisms contributed by other recipes (rsync `--exclude` lists, deploy-time cleanup) have to be mirrored into `.gitattributes` or `git_drift_skip_worktree_paths`
- `git_drift_skip_worktree_paths` entries are resolved against the files tracked at HEAD instead of being passed on verbatim. A directory now covers everything tracked below it, the same way a shared path does, so `Build` no longer has to be spelled out file by file
- `git_drift_ignore_paths` entries are appended through the same append-if-missing helper as the automatically derived exclude entries, replacing one `run()` per configured path with a single batched call and avoiding duplicate lines in `.git/info/exclude`

### Fixed

- `git-drift:init` no longer leaves a `.git` without a HEAD behind when one of its own steps fails (an SSH host key the server has not accepted, a missing deploy key). Init failures are non-fatal by design, so such a release was reported as "already initialized" on every later deploy, while `git-drift:check` read the whole release as untracked drift and then aborted the deployment with `fatal: ambiguous argument 'HEAD'`. The baseline is now built in a temporary Git directory and moved into place only once HEAD resolves, and a HEAD-less `.git` left behind by an earlier version is replaced by the next `git-drift:init`
- `git-drift:check` and `git-drift:status` verify that HEAD resolves instead of only testing for a `.git` directory, and skip with a notice when it does not
- A `git_drift_skip_worktree_paths` entry that covers no tracked file is reported and skipped instead of being handed to `git update-index`, which fails on a path that is not in the index. Inside `git-drift:check` that aborted the deployment, so a typo in the configuration was enough to break deploys
- The recipe no longer depends on the project's Composer autoloader being active. Requiring `src/GitDrift.php` as documented was not enough under a globally installed Deployer or `deployer.phar`, where nothing maps the package namespace: `GitDriftIndexPlanner`, extracted in 0.2.0, then failed to load and the deployment died with a fatal "class not found" at the first drift reconciliation. The classes are loaded as a fallback when no autoloader can supply them

## [0.2.1] — 2026-07-02

### Changed

- **BREAKING:** Package renamed from `oliver-thiele/deployer-git-drift` to `oliverthiele/deployer-git-drift` to match the vendor namespace used by all other packages published under this account. Update your `composer.json` require accordingly. The old `oliver-thiele/deployer-git-drift` package will be removed from Packagist.

## [0.2.0] — 2026-07-01

### Changed

- First release without an alpha/beta pre-release qualifier. Verified in real deployments (TYPO3, shared `data`/`.htaccess`/export-ignored `.gitattributes`) with no false-positive drift and correct detection of genuine server-side changes. Still pre-1.0: task names and configuration keys may change based on feedback from wider use.

## [0.1.5-alpha6] — 2026-07-01

### Changed

- Batch all `git-drift` index reconciliation into a constant number of remote calls (`git ls-tree`, `git update-index --index-info`, `git update-index --skip-worktree --stdin`) instead of up to three `run()` round-trips per affected file, so releases with many shared/export-ignored paths no longer scale linearly in deploy time
- Removed alpha-specific installation instructions and the hardcoded version number from the README's status banner, and clarified that shared symlinks are also written to `.git/info/exclude` automatically

## [0.1.4-alpha5] — 2026-07-01

### Added

- Automatic drift-suppression for `shared_dirs`/`shared_files`: tracked files shadowed by Deployer's shared symlinks are detected from Deployer's own config and marked `--skip-worktree`, without any project-specific path list
- Automatic drift-suppression for `.gitattributes` `export-ignore`d files, detected via a `git archive` comparison against the tracked file list
- `git_drift_skip_worktree_paths` option for manually marking additional tracked files (e.g. server-rewritten config) as expected to differ

### Changed

- `git-drift:check` and `git-drift:status` now reconcile the release index before reading status, replacing the need for project-level workaround tasks previously required for shared directories and export-ignored files
- Raised minimum PHP version from 8.2 to 8.4
- Extracted the skip-worktree decision logic into `GitDriftIndexPlanner`, a pure, unit-tested class decoupled from Deployer's runtime
- Added PHPStan (level 6), PHP-CS-Fixer, and PHPUnit as dev tooling

## [0.1.3-alpha4] — 2026-07-01

### Fixed

- Add `--ignore-submodules=all` to `git diff --stat HEAD` in `git-drift:check` and `git-drift:status` for consistent submodule handling

## [0.1.2-alpha3] — 2026-06-30

### Fixed

- Replace `<warning>` output tag with `<comment>` — `<warning>` was rendered as literal text instead of styled output

## [0.1.1-alpha2] — 2026-06-30

### Changed

- Extended Deployer compatibility to `^7.0 || ^8.0`

## [0.1.0-alpha1] — 2026-06-30

### Added
- `git-drift:init` task to initialize shallow Git tracking in the release directory after deployment
- `git-drift:check` task to detect server-side file changes before the next deployment
- `git-drift:status` task for manual drift inspection without triggering a deployment
- `git_drift_abort_on_drift` option to always abort on drift (default: `false` — ask interactively)
- `git_drift_ignore_paths` option to exclude paths from drift detection (e.g., caches, uploads, vendor)
