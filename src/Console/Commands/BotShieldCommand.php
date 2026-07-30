<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Console\Commands;

use Illuminate\Console\Command;

class BotShieldCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'bot-shield:placeholder';

    /**
     * The command description.
     */
    protected $description = 'Placeholder Artisan command shipped by the package bot-shield.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->line('BotShield placeholder command executed.');

        return self::SUCCESS;
    }
}
