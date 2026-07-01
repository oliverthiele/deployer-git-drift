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
     * @param string[] $manualSkipWorktreePaths Additional tracked files to always treat as expected to differ
     */
    public static function plan(
        array $sharedPaths,
        array $trackedFiles,
        array $archivedFiles,
        array $manualSkipWorktreePaths = [],
    ): GitDriftIndexPlan {
        $normalizedSharedPaths = self::normalizeSharedPaths($sharedPaths);
        $archivedLookup = array_flip($archivedFiles);

        $skipWorktreePaths = [];

        foreach ($trackedFiles as $file) {
            if (self::isUnderAnySharedPath($file, $normalizedSharedPaths) || !isset($archivedLookup[$file])) {
                $skipWorktreePaths[$file] = true;
            }
        }

        foreach ($manualSkipWorktreePaths as $file) {
            $skipWorktreePaths[$file] = true;
        }

        return new GitDriftIndexPlan(
            array_keys($skipWorktreePaths),
            $normalizedSharedPaths,
        );
    }

    /**
     * @param string[] $sharedPaths
     * @return string[]
     */
    private static function normalizeSharedPaths(array $sharedPaths): array
    {
        $normalized = [];
        foreach ($sharedPaths as $sharedPath) {
            $trimmed = trim($sharedPath, '/');
            if ($trimmed !== '') {
                $normalized[$trimmed] = true;
            }
        }

        return array_keys($normalized);
    }

    /**
     * @param string[] $sharedPaths Already normalized via normalizeSharedPaths()
     */
    private static function isUnderAnySharedPath(string $file, array $sharedPaths): bool
    {
        foreach ($sharedPaths as $sharedPath) {
            if ($file === $sharedPath || str_starts_with($file, $sharedPath . '/')) {
                return true;
            }
        }

        return false;
    }
}
