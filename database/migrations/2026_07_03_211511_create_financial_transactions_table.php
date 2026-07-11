<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->id();

            $table->string('type')->default('income');
            $table->string('description');
            $table->decimal('amount', 10, 2)->default(0);

            $table->date('due_date')->nullable();
            $table->dateTime('paid_at')->nullable();

            $table->string('status')->default('pending');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
    }
};