<?php

declare(strict_types=1);

namespace OliverThiele\DeployerGitDrift;

/**
 * Reads the commit a release was built from.
 *
 * Deployer's deploy:update_code writes it to REVISION in every release. The baseline has
 * to be built from exactly that commit, not from the tip of the deployed branch: a push
 * that lands while a deployment is running moves the tip past the shipped code, and the
 * next git-drift:check then reports the difference between the two commits as drift.
 *
 * Pure computation, like GitDriftIndexPlanner, so it can be tested without Deployer.
 */
final class GitDriftRevision
{
    /**
     * Returns the full commit hash from the content of a REVISION file, or null when the
     * file is empty or holds anything else — releases built by other recipes, or by a
     * Deployer version that did not write the file.
     *
     * Only a full SHA-1 or SHA-256 hash is accepted. The value ends up in a shell command,
     * and anything shorter or different would not be a reliable reference to fetch.
     */
    public static function fromRevisionFile(string $content): ?string
    {
        $revision = strtolower(trim($content));

        return preg_match('/^(?:[0-9a-f]{40}|[0-9a-f]{64})$/', $revision) === 1 ? $revision : null;
    }
}
