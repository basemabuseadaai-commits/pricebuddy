<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The product that holds the tracked results. Kept if the search is deleted.
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->json('queries');
            $table->string('brand')->nullable();
            $table->json('must_include')->nullable();
            $table->json('exclude')->nullable();
            $table->decimal('min_price', 12, 2)->nullable();
            $table->decimal('max_price', 12, 2)->nullable();
            $table->unsignedSmallInteger('per_shop')->default(3);
            // Null = every enabled product source.
            $table->json('product_source_ids')->nullable();
            $table->unsignedInteger('interval_minutes')->default(120);
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->json('last_result')->nullable();
            // Normalized URL => last time a run picked it.
            $table->json('seen_urls')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_searches');
    }
};
