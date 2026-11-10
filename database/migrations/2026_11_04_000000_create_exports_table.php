<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('exports.table', 'exports'), function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            match (config('exports.user_key_type', 'integer')) {
                'uuid' => $table->nullableUuidMorphs('user'),
                'ulid' => $table->nullableUlidMorphs('user'),
                'string' => $this->nullableStringMorphs($table),
                default => $table->nullableMorphs('user'),
            };

            $table->string('type')->nullable()->index();
            $table->string('export_class');
            $table->string('name');
            $table->string('format', 10);
            $table->string('status', 20)->index();
            $table->unsignedBigInteger('total_rows')->nullable();
            $table->unsignedBigInteger('processed_rows')->default(0);
            $table->string('disk')->nullable();
            $table->string('path')->nullable();
            $table->string('filename')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('exports.table', 'exports'));
    }

    protected function nullableStringMorphs(Blueprint $table): void
    {
        $table->string('user_type')->nullable();
        $table->string('user_id')->nullable();
        $table->index(['user_type', 'user_id']);
    }
};
