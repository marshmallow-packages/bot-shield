<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Marshmallow\BotShield\Agents\AgentRules;
use Marshmallow\BotShield\Enums\AgentAction;

class RobotsCommand extends Command
{
    protected $signature = 'bot-shield:robots
        {--append= : Append the stanzas to this file instead of printing them}';

    protected $description = 'Print robots.txt stanzas for every denied agent rule.';

    public function handle(AgentRules $rules, Filesystem $files): int
    {
        $denied = $rules->matching(AgentAction::Deny);

        if ($denied === []) {
            $this->components->warn('No agent rules use the "deny" action, so there is nothing to disallow.');

            return self::SUCCESS;
        }

        $patterns = [];

        foreach ($denied as $rule) {
            if ($rule->regex) {
                $this->components->warn(
                    "Skipping the regular expression rule [{$rule->pattern}]: robots.txt matches agent names literally.",
                );

                continue;
            }

            $patterns[] = $rule->pattern;
        }

        if ($patterns === []) {
            $this->components->warn('Every denied rule is a regular expression, so none can be expressed in robots.txt.');

            return self::SUCCESS;
        }

        $stanzas = $this->stanzas($patterns);

        $target = $this->option('append');

        if (! is_string($target) || $target === '') {
            foreach (explode(PHP_EOL, rtrim($stanzas, PHP_EOL)) as $line) {
                $this->line($line);
            }

            return self::SUCCESS;
        }

        if (! $files->exists($target)) {
            $this->components->error("The file [{$target}] does not exist.");

            return self::FAILURE;
        }

        $files->append($target, PHP_EOL.$stanzas);

        $this->components->info(sprintf('Appended %d stanza(s) to [%s].', count($patterns), $target));

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $patterns
     */
    private function stanzas(array $patterns): string
    {
        $lines = ['# Managed by marshmallow/bot-shield'];

        foreach ($patterns as $pattern) {
            $lines[] = '';
            $lines[] = "User-agent: {$pattern}";
            $lines[] = 'Disallow: /';
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }
}
