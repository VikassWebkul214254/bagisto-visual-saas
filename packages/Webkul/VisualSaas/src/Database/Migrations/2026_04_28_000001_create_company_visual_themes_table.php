<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_visual_themes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id');
            $table->string('theme_code');
            $table->string('name')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('current_version')->nullable();
            $table->timestamp('last_published_at')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->unique(['company_id', 'theme_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_visual_themes');
    }
};
