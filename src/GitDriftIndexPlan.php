<?php

declare(strict_types=1);

namespace OliverThiele\DeployerGitDrift;

final class GitDriftIndexPlan
{
    /**
     * @param string[] $skipWorktreePaths Tracked files to mark with `git update-index --skip-worktree`
     * @param string[] $excludeEntries Entries to append to `.git/info/exclude`
     */
    public function __construct(
        public readonly array $skipWorktreePaths,
        public readonly array $excludeEntries,
    ) {
    }
}
