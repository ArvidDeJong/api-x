<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_posts', function (Blueprint $table): void {
            $table->id();
            $table->text('text');
            $table->string('image')->nullable();
            $table->string('status', 16)->index();
            $table->string('x_id')->nullable();
            $table->string('media_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_posts');
    }
};
