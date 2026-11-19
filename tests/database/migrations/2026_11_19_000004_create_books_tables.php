<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBooksTables extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('isbn')->nullable();
            // json layout
            $table->json('title')->nullable();
            $table->json('summary')->nullable();
            $table->json('rank')->nullable();
            // columns layout
            $table->string('title_en')->nullable();
            $table->string('title_ar')->nullable();
            $table->text('summary_en')->nullable();
            $table->text('summary_ar')->nullable();
            $table->integer('rank_en')->nullable();
            $table->integer('rank_ar')->nullable();
            $table->timestamps();
        });

        Schema::create('book_translations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('book_id');
            $table->string('locale', 35);
            $table->string('title')->nullable();
            $table->text('summary')->nullable();
            $table->integer('rank')->nullable();
            $table->string('translator')->nullable();
            $table->unique(['book_id', 'locale']);
            $table->foreign('book_id')->references('id')->on('books')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_translations');
        Schema::dropIfExists('books');
    }
}
