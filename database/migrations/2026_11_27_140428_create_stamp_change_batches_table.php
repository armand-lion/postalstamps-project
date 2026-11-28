<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stamp_change_batches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('user_name')->nullable();

            $table->string('operation', 50);

            $table->text('description')->nullable();

            $table->unsignedInteger('stamp_count')->default(0);

            $table->json('operations')->nullable();

            $table->timestamps();

            $table->index('created_at');
            $table->index('operation');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stamp_change_batches');
    }
};
