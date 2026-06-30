<?php

declare(strict_types=1);

namespace Deployer;

set('git_drift_abort_on_drift', false);
set('git_drift_ignore_paths', []);

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

    $statusOutput = run('git -C {{current_path}} status --porcelain --ignore-submodules=all');

    if (empty(trim($statusOutput))) {
        writeln('<info>✓ No drift detected</info>');
        return;
    }

    writeln('');
    writeln('<error>⚠ Server drift detected:</error>');
    writeln('');

    $diffStatOutput = run('git -C {{current_path}} diff --stat HEAD');
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

    writeln('');
    writeln('<info>Git drift status for current release:</info>');
    writeln('');
    writeln(run('git -C {{current_path}} status --ignore-submodules=all'));

    $diffStatOutput = run('git -C {{current_path}} diff --stat HEAD');
    if (!empty(trim($diffStatOutput))) {
        writeln('');
        writeln($diffStatOutput);
    }
})->desc('Show current server drift status without deploying');
