<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_reconciliations', function (Blueprint $table): void {
            $table->uuid('group_uuid')->nullable()->after('id');
            $table->string('group_kind', 30)->default('one_to_one')->after('match_kind');

            $table->index(['clinic_id', 'group_uuid', 'status'], 'financial_reconciliations_group_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('financial_reconciliations', function (Blueprint $table): void {
            $table->dropIndex('financial_reconciliations_group_status_index');
            $table->dropColumn(['group_uuid', 'group_kind']);
        });
    }
};
