<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lamentor_form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('lamentor_documents')->cascadeOnDelete();
            $table->string('element_id', 32);
            $table->string('form_name')->default('');
            $table->longText('data');
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index(['document_id', 'element_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lamentor_form_submissions');
    }
};
