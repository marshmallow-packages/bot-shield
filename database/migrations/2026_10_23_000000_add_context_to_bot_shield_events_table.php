<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_shield_events', function (Blueprint $table): void {
            // Why a verification or trip failed: provider error codes, the
            // honeypot check that fired, and the like. Never the form payload.
            $table->json('context')->nullable()->after('action');
        });
    }
};
