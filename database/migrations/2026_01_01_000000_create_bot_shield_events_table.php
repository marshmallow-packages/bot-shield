<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_shield_events', function (Blueprint $table): void {
            $table->id();

            $table->string('type')->index();
            $table->string('outcome')->nullable();

            // Captcha scores are reported to one decimal, stored with room to spare.
            $table->decimal('score', 4, 3)->nullable();

            $table->string('form')->nullable();
            $table->string('component')->nullable();
            $table->string('action')->nullable();

            $table->text('url')->nullable();
            $table->string('ip', 64)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('created_at')->nullable()->index();

            $table->index(['type', 'created_at']);
        });
    }
};
