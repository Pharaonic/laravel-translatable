<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTypedItemsTable extends Migration
{
    public function up(): void
    {
        Schema::create('typed_items', function (Blueprint $table) {
            $table->bigIncrements('id');

            foreach (['flag', 'tags', 'meta', 'price', 'released_on', 'starts_at'] as $column) {
                $table->json($column)->nullable();
            }

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('typed_items');
    }
}
