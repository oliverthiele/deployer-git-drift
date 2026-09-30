<?php

declare(strict_types=1);

namespace OliverThiele\DeployerGitDrift\Tests;

use OliverThiele\DeployerGitDrift\GitDriftRevision;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GitDriftRevisionTest extends TestCase
{
    public function testSha1HashWithTrailingNewlineIsReturned(): void
    {
        // deploy:update_code writes the hash with `echo`, so the file ends with a newline
        self::assertSame(
            'a94a8fe5ccb19ba61c4c0873d391e987982fbbd3',
            GitDriftRevision::fromRevisionFile("a94a8fe5ccb19ba61c4c0873d391e987982fbbd3\n")
        );
    }

    public function testSha256HashIsReturned(): void
    {
        $hash = str_repeat('0123456789abcdef', 4);

        self::assertSame($hash, GitDriftRevision::fromRevisionFile($hash));
    }

    public function testUppercaseHashIsNormalized(): void
    {
        self::assertSame(
            'a94a8fe5ccb19ba61c4c0873d391e987982fbbd3',
            GitDriftRevision::fromRevisionFile('A94A8FE5CCB19BA61C4C0873D391E987982FBBD3')
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidContentProvider(): array
    {
        return [
            'missing file' => [''],
            'whitespace only' => ["\n"],
            'abbreviated hash' => ['a94a8fe'],
            'branch name' => ['develop'],
            'hash with shell suffix' => ['a94a8fe5ccb19ba61c4c0873d391e987982fbbd3; rm -rf /'],
            'two hashes' => ["a94a8fe5ccb19ba61c4c0873d391e987982fbbd3\nda39a3ee5e6b4b0d3255bfef95601890afd80709"],
        ];
    }

    #[DataProvider('invalidContentProvider')]
    public function testAnythingButAFullHashIsRejected(string $content): void
    {
        self::assertNull(GitDriftRevision::fromRevisionFile($content));
    }
}
