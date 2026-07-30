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
        'BOT_SHIELD_RECAPTCHA_SITE_KEY' => '',
        'BOT_SHIELD_RECAPTCHA_SECRET_KEY' => '',
        'BOT_SHIELD_RECAPTCHA_SCORE' => '0.6',
    ];

    private const string ANCHOR = '->withExceptions(function (Exceptions $exceptions) {';

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

        if (! str_contains($contents, self::ANCHOR)) {
            return self::NO_ANCHOR;
        }

        $contents = str_replace(
            self::ANCHOR,
            self::ANCHOR.PHP_EOL.'        BotShield::handles($exceptions);',
            $contents,
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
