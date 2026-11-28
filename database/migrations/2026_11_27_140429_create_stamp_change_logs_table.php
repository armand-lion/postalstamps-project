<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stamp_change_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('stamp_change_batch_id')
                ->constrained('stamp_change_batches')
                ->restrictOnDelete();

            $table->unsignedBigInteger('stamp_id');

            $table->string('stamp_name');

            $table->json('old_values');

            $table->json('new_values');

            $table->timestamps();

            $table->index('stamp_id');

            $table->unique([
                'stamp_change_batch_id',
                'stamp_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stamp_change_logs');
    }
};
