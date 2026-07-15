<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('permissions')) {
            return;
        }

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->string('group')->nullable();

            $table->boolean('active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index('active');
            $table->index('group');
        });
    }

    public function down(): void
    {
        //
    }
};
