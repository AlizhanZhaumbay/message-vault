<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('teaser')->nullable();
            $table->text('body')->nullable();

            $table->timestamp('unlock_at')->nullable()->index();
            $table->string('destroy_mode');
            $table->unsignedInteger('max_views')->nullable();
            $table->timestamp('destroy_at')->nullable();

            $table->unsignedInteger('views_count')->default(0);
            $table->timestamp('first_viewed_at')->nullable();

            $table->string('password_hash')->nullable();
            $table->string('owner_token_hash', 64);

            $table->timestamp('destroyed_at')->nullable()->index();
            $table->string('destroyed_reason')->nullable();

            $table->timestamps();

            $table->index(['destroy_mode', 'destroy_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
