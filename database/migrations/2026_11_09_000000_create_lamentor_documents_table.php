<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lamentor_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title')->default('');
            $table->string('slug')->nullable()->unique();
            // page, section, header, footer, popup ... (free-form; Lamentor only stores it)
            $table->string('type')->default('page');
            $table->string('status')->default('draft');
            // The Elementor element tree (_elementor_data).
            $table->longText('data')->nullable();
            // Page settings (background, margins ...).
            $table->longText('settings')->nullable();
            $table->unsignedBigInteger('author_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('lamentor_document_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('lamentor_documents')->cascadeOnDelete();
            $table->longText('data')->nullable();
            $table->longText('settings')->nullable();
            $table->unsignedBigInteger('author_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lamentor_document_revisions');
        Schema::dropIfExists('lamentor_documents');
    }
};
