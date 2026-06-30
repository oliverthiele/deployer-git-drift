# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
