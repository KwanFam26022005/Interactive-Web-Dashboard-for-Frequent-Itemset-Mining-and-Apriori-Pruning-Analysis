<?php

declare(strict_types=1);

namespace App\Experiments;

class LineageHelper
{
    /**
     * Gets the full 40-character git commit SHA of HEAD.
     *
     * @param string $repoRoot Path to repository root
     * @return string|null Full 40-character SHA, or null if not obtainable
     */
    public static function getGitHeadSha(string $repoRoot): ?string
    {
        $cmd = 'git -C ' . escapeshellarg($repoRoot) . ' rev-parse HEAD';
        $output = @shell_exec($cmd);
        if ($output === null) {
            return null;
        }

        $trimmed = trim($output);
        if (preg_match('/^[0-9a-f]{40}$/i', $trimmed) === 1) {
            return strtolower($trimmed);
        }

        return null;
    }

    /**
     * Checks whether the git working tree has uncommitted modifications.
     *
     * @param string $repoRoot Path to repository root
     * @return bool True if worktree is clean, false if dirty or check fails
     */
    public static function isGitWorktreeClean(string $repoRoot): bool
    {
        $cmd = 'git -C ' . escapeshellarg($repoRoot) . ' status --porcelain';
        $output = @shell_exec($cmd);
        if ($output === null) {
            $outLines = [];
            $exitCode = 1;
            @exec($cmd, $outLines, $exitCode);
            return $exitCode === 0 && empty($outLines);
        }

        return trim($output) === '';
    }

    /**
     * Computes the SHA-256 hash of a file on disk.
     *
     * @param string $filePath Path to file
     * @return string|null Hexadecimal SHA-256 hash, or null if file is unreadable
     */
    public static function hashFile(string $filePath): ?string
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            return null;
        }

        $hash = @hash_file('sha256', $filePath);
        return $hash !== false ? strtolower($hash) : null;
    }

    /**
     * Computes the SHA-256 hash of raw string content.
     *
     * @param string $content Content string
     * @return string Hexadecimal SHA-256 hash
     */
    public static function hashContent(string $content): string
    {
        return strtolower(hash('sha256', $content));
    }
}
