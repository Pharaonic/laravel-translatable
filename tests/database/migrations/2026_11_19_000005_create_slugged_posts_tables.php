<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSluggedPostsTables extends Migration
{
    public function up(): void
    {
        Schema::create('slugged_table_posts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->timestamps();
        });

        Schema::create('slugged_post_translations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('post_id');
            $table->string('locale', 35);
            $table->string('title')->nullable();
            $table->string('description')->nullable();
            $table->string('slug')->nullable();
            $table->unique(['post_id', 'locale']);
        });

        Schema::create('slugged_json_posts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->json('title')->nullable();
            $table->json('description')->nullable();
            $table->json('slug')->nullable();
            $table->timestamps();
        });

        Schema::create('slugged_columns_posts', function (Blueprint $table) {
            $table->bigIncrements('id');

            foreach (['title', 'description', 'slug'] as $field) {
                foreach (['en', 'ar'] as $locale) {
                    $table->string($field.'_'.$locale)->nullable();
                }
            }

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slugged_columns_posts');
        Schema::dropIfExists('slugged_json_posts');
        Schema::dropIfExists('slugged_post_translations');
        Schema::dropIfExists('slugged_table_posts');
    }
}
