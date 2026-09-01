<?php

declare(strict_types=1);

namespace OliverThiele\DeployerGitDrift;

/**
 * Computes which tracked files must be marked --skip-worktree to avoid false-positive drift.
 *
 * Two categories of tracked files legitimately differ from HEAD in a deployed release:
 *
 * - Files inside shared_dirs/shared_files: Deployer replaces these paths with symlinks into
 *   shared/, so their content is intentionally different from the Git blob.
 * - Files marked `export-ignore` in .gitattributes: absent from the deployed archive
 *   entirely, so they always look "deleted" compared to HEAD.
 * - Files kept off the server by a mechanism Git knows nothing about, such as an rsync
 *   --exclude list contributed by another recipe. These cannot be derived from anywhere,
 *   so they are named explicitly through git_drift_skip_worktree_paths.
 *
 * This class is pure computation over already-gathered file lists — it has no knowledge of
 * Git, Deployer, or the filesystem, which keeps it unit-testable without a real repository.
 */
final class GitDriftIndexPlanner
{
    /**
     * @param string[] $sharedPaths Deployer's shared_dirs + shared_files, as configured
     * @param string[] $trackedFiles All files tracked at HEAD (git ls-tree -r HEAD --name-only)
     * @param string[] $archivedFiles Files present in the deployed archive (respects export-ignore)
     * @param string[] $manualSkipWorktreePaths Configured paths to treat as expected to differ: a tracked
     *                                            file, or a directory standing for everything below it
     */
    public static function plan(
        array $sharedPaths,
        array $trackedFiles,
        array $archivedFiles,
        array $manualSkipWorktreePaths = [],
    ): GitDriftIndexPlan {
        $normalizedSharedPaths = self::normalizePaths($sharedPaths);
        $archivedLookup = array_flip($archivedFiles);

        $skipWorktreePaths = [];

        foreach ($trackedFiles as $file) {
            if (self::isUnderAnyPath($file, $normalizedSharedPaths) || !isset($archivedLookup[$file])) {
                $skipWorktreePaths[$file] = true;
            }
        }

        // Configured paths are resolved against the tracked files rather than passed on
        // verbatim. A directory then covers everything below it, the way a shared path
        // does, and an entry covering nothing is reported instead of being handed to
        // `git update-index`, which fails on a path that is not in the index — inside
        // git-drift:check that would abort the deployment over a typo.
        $unmatchedSkipWorktreePaths = [];
        foreach (self::normalizePaths($manualSkipWorktreePaths) as $configuredPath) {
            $matchedFiles = array_filter(
                $trackedFiles,
                static fn (string $file): bool => self::isUnderPath($file, $configuredPath),
            );

            if ($matchedFiles === []) {
                $unmatchedSkipWorktreePaths[] = $configuredPath;
                continue;
            }

            foreach ($matchedFiles as $file) {
                $skipWorktreePaths[$file] = true;
            }
        }

        return new GitDriftIndexPlan(
            array_keys($skipWorktreePaths),
            $normalizedSharedPaths,
            $unmatchedSkipWorktreePaths,
        );
    }

    /**
     * @param string[] $paths
     * @return string[]
     */
    private static function normalizePaths(array $paths): array
    {
        $normalized = [];
        foreach ($paths as $path) {
            $trimmed = trim($path, '/');
            if ($trimmed !== '') {
                $normalized[$trimmed] = true;
            }
        }

        return array_keys($normalized);
    }

    /**
     * @param string[] $paths Already normalized via normalizePaths()
     */
    private static function isUnderAnyPath(string $file, array $paths): bool
    {
        foreach ($paths as $path) {
            if (self::isUnderPath($file, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $path Already normalized via normalizePaths()
     */
    private static function isUnderPath(string $file, string $path): bool
    {
        return $file === $path || str_starts_with($file, $path . '/');
    }
}
