<?php

declare(strict_types=1);

namespace OliverThiele\DeployerGitDrift\Tests;

use OliverThiele\DeployerGitDrift\GitDriftIndexPlanner;
use PHPUnit\Framework\TestCase;

final class GitDriftIndexPlannerTest extends TestCase
{
    public function testFileUnderSharedDirectoryIsMarkedSkipWorktree(): void
    {
        $plan = GitDriftIndexPlanner::plan(
            sharedPaths: ['data'],
            trackedFiles: ['data/.gitkeep', 'public/index.php'],
            archivedFiles: ['data/.gitkeep', 'public/index.php'],
        );

        self::assertSame(['data/.gitkeep'], $plan->skipWorktreePaths);
    }

    public function testExactMatchOnSharedFileIsMarkedSkipWorktree(): void
    {
        $plan = GitDriftIndexPlanner::plan(
            sharedPaths: ['.env'],
            trackedFiles: ['.env', 'public/index.php'],
            archivedFiles: ['.env', 'public/index.php'],
        );

        self::assertSame(['.env'], $plan->skipWorktreePaths);
    }

    public function testSharedPathDoesNotMatchByPrefixWithoutSlashBoundary(): void
    {
        $plan = GitDriftIndexPlanner::plan(
            sharedPaths: ['data'],
            trackedFiles: ['data-backup/file.txt', 'public/index.php'],
            archivedFiles: ['data-backup/file.txt', 'public/index.php'],
        );

        self::assertSame([], $plan->skipWorktreePaths);
    }

    public function testExportIgnoredFileAbsentFromArchiveIsMarkedSkipWorktree(): void
    {
        $plan = GitDriftIndexPlanner::plan(
            sharedPaths: [],
            trackedFiles: ['packages/foo/.gitattributes', 'public/index.php'],
            archivedFiles: ['public/index.php'],
        );

        self::assertSame(['packages/foo/.gitattributes'], $plan->skipWorktreePaths);
    }

    public function testFileMatchingBothCategoriesIsListedOnce(): void
    {
        $plan = GitDriftIndexPlanner::plan(
            sharedPaths: ['data'],
            trackedFiles: ['data/.gitkeep'],
            archivedFiles: [],
        );

        self::assertSame(['data/.gitkeep'], $plan->skipWorktreePaths);
    }

    public function testManualSkipWorktreePathsAreAlwaysIncluded(): void
    {
        $plan = GitDriftIndexPlanner::plan(
            sharedPaths: [],
            trackedFiles: ['public/.htaccess'],
            archivedFiles: ['public/.htaccess'],
            manualSkipWorktreePaths: ['public/.htaccess'],
        );

        self::assertSame(['public/.htaccess'], $plan->skipWorktreePaths);
    }

    public function testCleanFileIsNotMarkedSkipWorktree(): void
    {
        $plan = GitDriftIndexPlanner::plan(
            sharedPaths: ['data'],
            trackedFiles: ['public/index.php'],
            archivedFiles: ['public/index.php'],
        );

        self::assertSame([], $plan->skipWorktreePaths);
    }

    public function testSharedPathsAreNormalizedAndDeduplicatedForExcludeEntries(): void
    {
        $plan = GitDriftIndexPlanner::plan(
            sharedPaths: ['/data/', 'data', 'public/fileadmin/', ''],
            trackedFiles: [],
            archivedFiles: [],
        );

        self::assertSame(['data', 'public/fileadmin'], $plan->excludeEntries);
    }
}
