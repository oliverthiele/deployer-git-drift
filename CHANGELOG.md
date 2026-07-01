# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
