<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Installation;

use Illuminate\Filesystem\Filesystem;

/**
 * The file edits bot-shield:install performs, separated from the command so they
 * can be exercised against real files rather than an application skeleton.
 */
final class Installer
{
    public const string WIRED = 'wired';

    public const string ALREADY_WIRED = 'already-wired';

    public const string MISSING_FILE = 'missing-file';

    public const string NO_ANCHOR = 'no-anchor';

    /**
     * The env keys worth having in .env.example, with their documented defaults.
     */
    public const array ENV_KEYS = [
        'BOT_SHIELD_ENABLED' => 'true',
        'BOT_SHIELD_RECAPTCHA_ENABLED' => 'true',
        'BOT_SHIELD_RECAPTCHA_SITE_KEY' => '',
        'BOT_SHIELD_RECAPTCHA_SECRET_KEY' => '',
        'BOT_SHIELD_RECAPTCHA_SCORE' => '0.6',
    ];

    /**
     * The opening line of the application's withExceptions() closure.
     *
     * Deliberately not a literal string: a return type on the closure is
     * required by most style guides, the parameter can be named anything, and
     * the class may be imported or written out in full. Matching the exact
     * default skeleton spelling meant the installer failed on any file written
     * to a standard, which is most of them.
     */
    private const string ANCHOR_PATTERN = '/^(?<indent>[ \t]*)->withExceptions\(\s*(?:static\s+)?function\s*\(\s*(?:\\\\?[A-Za-z_\x80-\xff][\w\x80-\xff]*(?:\\\\[A-Za-z_\x80-\xff][\w\x80-\xff]*)*\\\\)?Exceptions\s+\$(?<parameter>\w+)\s*\)\s*(?::\s*void\s*)?\{/m';

    private const string IMPORT = 'use Marshmallow\BotShield\Facades\BotShield;';

    public function __construct(
        private readonly Filesystem $files,
    ) {}

    /**
     * @return self::WIRED|self::ALREADY_WIRED|self::MISSING_FILE|self::NO_ANCHOR
     */
    public function wireExceptionHandler(string $path): string
    {
        if (! $this->files->exists($path)) {
            return self::MISSING_FILE;
        }

        $contents = $this->files->get($path);

        if (str_contains($contents, 'BotShield::handles')) {
            return self::ALREADY_WIRED;
        }

        if (preg_match(self::ANCHOR_PATTERN, $contents, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return self::NO_ANCHOR;
        }

        /*
         * Inserted immediately after the opening brace rather than anywhere
         * else in the closure, so an existing body is never rewritten or
         * reordered. The call is added, nothing is taken away.
         */
        [$anchor, $offset] = $matches[0];
        [$indent] = $matches['indent'];
        [$parameter] = $matches['parameter'];

        $contents = substr_replace(
            $contents,
            $anchor.PHP_EOL.$indent.'    BotShield::handles($'.$parameter.');',
            $offset,
            strlen($anchor),
        );

        $this->files->put($path, $this->ensureImport($contents));

        return self::WIRED;
    }

    /**
     * @return list<string> The keys that were added.
     */
    public function addEnvKeys(string $path): array
    {
        if (! $this->files->exists($path)) {
            return [];
        }

        $contents = $this->files->get($path);
        $missing = [];

        foreach (self::ENV_KEYS as $key => $default) {
            if (str_contains($contents, $key.'=')) {
                continue;
            }

            $missing[$key] = $default;
        }

        if ($missing === []) {
            return [];
        }

        $lines = [''];

        foreach ($missing as $key => $default) {
            $lines[] = "{$key}={$default}";
        }

        $this->files->append($path, implode(PHP_EOL, $lines).PHP_EOL);

        return array_keys($missing);
    }

    private function ensureImport(string $contents): string
    {
        if (str_contains($contents, self::IMPORT)) {
            return $contents;
        }

        $position = strpos($contents, PHP_EOL.'use ');

        if ($position === false) {
            return $contents;
        }

        return substr_replace($contents, PHP_EOL.self::IMPORT, $position, 0);
    }
}
