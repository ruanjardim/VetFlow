<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('service_orders', 'duration_minutes')) {
                $table->unsignedSmallInteger('duration_minutes')->nullable()->after('scheduled_at');
            }

            if (! Schema::hasColumn('service_orders', 'checked_in_at')) {
                $table->dateTime('checked_in_at')->nullable()->after('duration_minutes');
            }

            if (! Schema::hasColumn('service_orders', 'recurrence_group')) {
                $table->string('recurrence_group', 36)->nullable()->after('checked_in_at');
                $table->index('recurrence_group');
            }
        });

        Schema::table('service_orders', function (Blueprint $table): void {
            $table->index(['clinic_id', 'scheduled_at'], 'service_orders_clinic_scheduled_index');
        });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->dropIndex('service_orders_clinic_scheduled_index');
            $table->dropIndex(['recurrence_group']);
            $table->dropColumn(['duration_minutes', 'checked_in_at', 'recurrence_group']);
        });
    }
};
