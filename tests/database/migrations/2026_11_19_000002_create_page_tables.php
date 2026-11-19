<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePageTables extends Migration
{
    public function up(): void
    {
        Schema::create('table_pages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('slug')->nullable();
            $table->timestamps();
        });

        Schema::create('table_page_translations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('table_page_id');
            $table->string('locale', 35);
            $table->string('title')->nullable();
            $table->text('summary')->nullable();
            $table->integer('rank')->nullable();
            $table->unique(['table_page_id', 'locale']);
            $table->foreign('table_page_id')->references('id')->on('table_pages')->onDelete('cascade');
        });

        Schema::create('json_pages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('slug')->nullable();
            $table->json('title')->nullable();
            $table->json('summary')->nullable();
            $table->json('rank')->nullable();
            $table->timestamps();
        });

        Schema::create('columns_pages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('slug')->nullable();
            $table->string('title_en')->nullable();
            $table->string('title_ar')->nullable();
            $table->text('summary_en')->nullable();
            $table->text('summary_ar')->nullable();
            $table->integer('rank_en')->nullable();
            $table->integer('rank_ar')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('columns_pages');
        Schema::dropIfExists('json_pages');
        Schema::dropIfExists('table_page_translations');
        Schema::dropIfExists('table_pages');
    }
}
