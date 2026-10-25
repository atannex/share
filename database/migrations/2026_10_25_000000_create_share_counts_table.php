<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('share_counts', function (Blueprint $table) {
            $table->id();
            // sha1 of the shared address (or of the subject for text-only shares).
            $table->char('url_hash', 40);
            $table->text('url')->nullable();
            $table->string('shareable_type')->nullable();
            $table->string('shareable_id', 64)->nullable();
            $table->string('platform', 40);
            $table->date('date');
            $table->unsignedInteger('total')->default(0);
            $table->timestamps();

            $table->unique(['url_hash', 'platform', 'date']);
            $table->index(['shareable_type', 'shareable_id']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_counts');
    }
};
