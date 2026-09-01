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

    public function testConfiguredFilePathIsMarkedSkipWorktree(): void
    {
        $plan = GitDriftIndexPlanner::plan(
            sharedPaths: [],
            trackedFiles: ['public/.htaccess'],
            archivedFiles: ['public/.htaccess'],
            manualSkipWorktreePaths: ['public/.htaccess'],
        );

        self::assertSame(['public/.htaccess'], $plan->skipWorktreePaths);
        self::assertSame([], $plan->unmatchedSkipWorktreePaths);
    }

    public function testConfiguredDirectoryCoversEveryTrackedFileBeneathIt(): void
    {
        $plan = GitDriftIndexPlanner::plan(
            sharedPaths: [],
            trackedFiles: ['Build/webpack.config.js', 'Build/src/app.js', 'public/index.php'],
            archivedFiles: ['Build/webpack.config.js', 'Build/src/app.js', 'public/index.php'],
            manualSkipWorktreePaths: ['Build'],
        );

        self::assertSame(['Build/webpack.config.js', 'Build/src/app.js'], $plan->skipWorktreePaths);
        self::assertSame([], $plan->unmatchedSkipWorktreePaths);
    }

    public function testConfiguredPathIsNormalizedLikeASharedPath(): void
    {
        $plan = GitDriftIndexPlanner::plan(
            sharedPaths: [],
            trackedFiles: ['Build/webpack.config.js'],
            archivedFiles: ['Build/webpack.config.js'],
            manualSkipWorktreePaths: ['/Build/'],
        );

        self::assertSame(['Build/webpack.config.js'], $plan->skipWorktreePaths);
    }

    public function testConfiguredPathDoesNotMatchByPrefixWithoutSlashBoundary(): void
    {
        $plan = GitDriftIndexPlanner::plan(
            sharedPaths: [],
            trackedFiles: ['Build-cache/app.js'],
            archivedFiles: ['Build-cache/app.js'],
            manualSkipWorktreePaths: ['Build'],
        );

        self::assertSame([], $plan->skipWorktreePaths);
        self::assertSame(['Build'], $plan->unmatchedSkipWorktreePaths);
    }

    public function testConfiguredPathCoveringNoTrackedFileIsReportedInsteadOfMarked(): void
    {
        $plan = GitDriftIndexPlanner::plan(
            sharedPaths: [],
            trackedFiles: ['public/index.php'],
            archivedFiles: ['public/index.php'],
            manualSkipWorktreePaths: ['public/.htacces', 'public/index.php'],
        );

        // The typo would make `git update-index` fail on a path that is not in the index.
        self::assertSame(['public/index.php'], $plan->skipWorktreePaths);
        self::assertSame(['public/.htacces'], $plan->unmatchedSkipWorktreePaths);
    }

    public function testFileMatchedByBothAConfiguredPathAndTheArchiveIsListedOnce(): void
    {
        $plan = GitDriftIndexPlanner::plan(
            sharedPaths: [],
            trackedFiles: ['Build/app.js'],
            archivedFiles: [],
            manualSkipWorktreePaths: ['Build'],
        );

        self::assertSame(['Build/app.js'], $plan->skipWorktreePaths);
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
