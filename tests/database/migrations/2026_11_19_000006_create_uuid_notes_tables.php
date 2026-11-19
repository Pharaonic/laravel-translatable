<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUuidNotesTables extends Migration
{
    public function up(): void
    {
        // One table carries the json (title) and columns (title_en, title_ar) layouts.
        Schema::create('uuid_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->json('title')->nullable();
            $table->string('title_en')->nullable();
            $table->string('title_ar')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('uuid_note_translations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid_note_id');
            $table->string('locale', 35);
            $table->string('title')->nullable();
            $table->unique(['uuid_note_id', 'locale']);
            $table->foreign('uuid_note_id')->references('id')->on('uuid_notes')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uuid_note_translations');
        Schema::dropIfExists('uuid_notes');
    }
}
