<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_documents', function (Blueprint $table): void {
            $table->string('cancellation_reason', 255)->nullable()->after('last_error_message');
            $table->dateTime('cancellation_requested_at')->nullable()->after('authorized_at');
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_documents', function (Blueprint $table): void {
            $table->dropColumn(['cancellation_reason', 'cancellation_requested_at']);
        });
    }
};
