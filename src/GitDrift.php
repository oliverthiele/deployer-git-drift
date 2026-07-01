<?php

declare(strict_types=1);

namespace Deployer;

use OliverThiele\DeployerGitDrift\GitDriftIndexPlanner;

set('git_drift_abort_on_drift', false);
set('git_drift_ignore_paths', []);
set('git_drift_skip_worktree_paths', []);

/**
 * Suppress known false positives from git-drift's status check.
 *
 * git-drift:check gates on `git status --porcelain`, which respects the skip-worktree bit.
 * `git diff --stat HEAD`, used only for the human-readable report, reads the working tree
 * directly and ignores the index — so once status is clean, diff is never even reached.
 * That is why skip-worktree (not `git rm --cached`) is the mechanism used here.
 *
 * Gathering the file lists and applying the result are Git/Deployer I/O and live here.
 * Deciding which files need --skip-worktree is pure computation and lives in
 * GitDriftIndexPlanner, which can be unit-tested without a real Git repository.
 *
 * Every step below is batched into a single `run()` call regardless of how many files are
 * involved — each `run()` is a synchronous round-trip to the release host, so one call per
 * file would scale badly on releases with many shared/export-ignored paths.
 */
function gitDriftReconcileIndex(string $path): void
{
    $sharedPaths = array_map('strval', array_merge((array)get('shared_dirs', []), (array)get('shared_files', [])));

    // "git ls-tree -r HEAD" (mode, type, hash, path) doubles as the plain tracked-file list
    // for the planner and as the source of hashes needed to restore index entries below —
    // one call instead of a separate --name-only call plus a rev-parse per file.
    $trackedFileInfo = gitDriftParseTrackedFiles(run("git -C $path ls-tree -r HEAD 2>/dev/null || true"));
    $trackedFiles = array_keys($trackedFileInfo);
    $archivedFiles = gitDriftLines(run("git -C $path archive HEAD 2>/dev/null | tar -t 2>/dev/null || true"));
    $manualSkipWorktreePaths = array_map('strval', (array)get('git_drift_skip_worktree_paths', []));

    $plan = GitDriftIndexPlanner::plan($sharedPaths, $trackedFiles, $archivedFiles, $manualSkipWorktreePaths);

    gitDriftAppendMissingExcludeEntries($path, $plan->excludeEntries);
    gitDriftRestoreIndexEntries($path, $plan->skipWorktreePaths, $trackedFileInfo);
    gitDriftMarkSkipWorktree($path, $plan->skipWorktreePaths);
}

/**
 * @return string[]
 */
function gitDriftLines(string $output): array
{
    return array_values(array_filter(array_map('trim', explode("\n", trim($output)))));
}

/**
 * Parses `git ls-tree -r HEAD` output ("<mode> <type> <hash>\t<path>" per line).
 *
 * @return array<string, array{0: string, 1: string, 2: string}> Path => [mode, type, hash]
 */
function gitDriftParseTrackedFiles(string $output): array
{
    $files = [];
    foreach (gitDriftLines($output) as $line) {
        $parts = explode("\t", $line, 2);
        if (count($parts) !== 2) {
            continue;
        }
        [$meta, $file] = $parts;

        $metaParts = explode(' ', $meta, 3);
        if (count($metaParts) !== 3) {
            continue;
        }

        $files[$file] = [$metaParts[0], $metaParts[1], $metaParts[2]];
    }

    return $files;
}

/**
 * The shared symlink itself must not show up as untracked. No trailing slash: Deployer
 * creates shared dirs as symlinks, and Git's "dir/" exclude syntax only matches real
 * directories, never a symlink of the same name.
 *
 * @param string[] $excludeEntries
 */
function gitDriftAppendMissingExcludeEntries(string $path, array $excludeEntries): void
{
    if ($excludeEntries === []) {
        return;
    }

    $existing = array_flip(gitDriftLines(run("cat $path/.git/info/exclude 2>/dev/null || true")));
    $missing = array_values(array_filter($excludeEntries, static fn (string $entry): bool => !isset($existing[$entry])));

    if ($missing === []) {
        return;
    }

    $payload = implode("\n", $missing) . "\n";
    run('printf \'%s\' ' . escapeshellarg($payload) . " >> $path/.git/info/exclude");
}

/**
 * Restores index entries removed by a legacy `git rm --cached` (from an older version of
 * this recipe) before they can be marked --skip-worktree, using the mode/type/hash already
 * known from `git ls-tree` — no per-file `git rev-parse` needed.
 *
 * @param string[] $skipWorktreePaths
 * @param array<string, array{0: string, 1: string, 2: string}> $trackedFileInfo
 */
function gitDriftRestoreIndexEntries(string $path, array $skipWorktreePaths, array $trackedFileInfo): void
{
    $lines = [];
    foreach ($skipWorktreePaths as $file) {
        if (isset($trackedFileInfo[$file])) {
            [$mode, $type, $hash] = $trackedFileInfo[$file];
            $lines[] = "$mode $type $hash\t$file";
        }
    }

    if ($lines === []) {
        return;
    }

    $payload = implode("\n", $lines) . "\n";
    run('printf \'%s\' ' . escapeshellarg($payload) . " | git -C $path update-index --add --index-info");
}

/**
 * @param string[] $skipWorktreePaths
 */
function gitDriftMarkSkipWorktree(string $path, array $skipWorktreePaths): void
{
    if ($skipWorktreePaths === []) {
        return;
    }

    $payload = implode("\n", $skipWorktreePaths) . "\n";
    run('printf \'%s\' ' . escapeshellarg($payload) . " | git -C $path update-index --skip-worktree --stdin");
}

task('git-drift:init', function (): void {
    try {
        if (test('[ -d "{{release_path}}/.git" ]')) {
            writeln('<info>✓ Git drift tracking already initialized</info>');
            return;
        }

        run('git -C {{release_path}} init --quiet');
        run('git -C {{release_path}} remote add origin {{repository}}');
        run('git -C {{release_path}} fetch origin {{branch}} --depth=1 --quiet');
        run('git -C {{release_path}} reset FETCH_HEAD --quiet');

        foreach ((array)get('git_drift_ignore_paths') as $ignoredPath) {
            run('echo ' . escapeshellarg((string)$ignoredPath) . ' >> {{release_path}}/.git/info/exclude');
        }

        gitDriftReconcileIndex('{{release_path}}');

        writeln('<info>✓ Git drift tracking initialized</info>');
    } catch (\Throwable $exception) {
        writeln('<comment>⚠ Git drift init failed (non-fatal): ' . $exception->getMessage() . '</comment>');
    }
})->desc('Initialize Git in release directory for drift tracking');

task('git-drift:check', function (): void {
    if (!test('[ -e "{{current_path}}" ]')) {
        writeln('<info>No current release found, skipping drift check.</info>');
        return;
    }

    if (!test('[ -d "{{current_path}}/.git" ]')) {
        writeln('<comment>⚠ Git not initialized in current release — drift check skipped.</comment>');
        writeln('<comment>After the first deploy with git-drift:init, subsequent deployments will be checked.</comment>');
        return;
    }

    gitDriftReconcileIndex('{{current_path}}');

    $statusOutput = run('git -C {{current_path}} status --porcelain --ignore-submodules=all');

    if (empty(trim($statusOutput))) {
        writeln('<info>✓ No drift detected</info>');
        return;
    }

    writeln('');
    writeln('<error>⚠ Server drift detected:</error>');
    writeln('');

    $diffStatOutput = run('git -C {{current_path}} diff --stat HEAD --ignore-submodules=all');
    if (!empty(trim($diffStatOutput))) {
        writeln($diffStatOutput);
    }

    $untrackedFilesOutput = run('git -C {{current_path}} ls-files --others --exclude-standard');
    if (!empty(trim($untrackedFilesOutput))) {
        writeln('<comment>Untracked files added on server:</comment>');
        writeln($untrackedFilesOutput);
    }

    writeln('');
    writeln('<comment>These changes were made directly on the server.</comment>');
    writeln('<comment>They will be LOST after this deployment.</comment>');
    writeln('');

    if ((bool)get('git_drift_abort_on_drift')) {
        throw new \RuntimeException('Deployment aborted: unresolved server drift detected.');
    }

    if (!askConfirmation('Continue deployment and discard changes?')) {
        throw new \RuntimeException('Deployment aborted: unresolved server drift.');
    }

    writeln('<comment>⚠ Continuing deployment — server-side changes will be overwritten.</comment>');
})->desc('Check for server-side file changes before deployment');

task('git-drift:status', function (): void {
    if (!test('[ -e "{{current_path}}" ]')) {
        writeln('<comment>No current release found.</comment>');
        return;
    }

    if (!test('[ -d "{{current_path}}/.git" ]')) {
        writeln('<comment>⚠ Git not initialized in current release.</comment>');
        writeln('<comment>Deploy once with git-drift:init enabled to start tracking.</comment>');
        return;
    }

    gitDriftReconcileIndex('{{current_path}}');

    writeln('');
    writeln('<info>Git drift status for current release:</info>');
    writeln('');
    writeln(run('git -C {{current_path}} status --ignore-submodules=all'));

    $diffStatOutput = run('git -C {{current_path}} diff --stat HEAD --ignore-submodules=all');
    if (!empty(trim($diffStatOutput))) {
        writeln('');
        writeln($diffStatOutput);
    }
})->desc('Show current server drift status without deploying');
