<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('patients', 'lifecycle_status')) {
            Schema::table('patients', function (Blueprint $table): void {
                $table->string('lifecycle_status', 20)->default('active')->after('pedigree_number');
                $table->text('lifecycle_reason')->nullable()->after('lifecycle_status');
                $table->timestamp('lifecycle_changed_at')->nullable()->after('lifecycle_reason');
                $table->foreignId('lifecycle_changed_by_user_id')
                    ->nullable()
                    ->after('lifecycle_changed_at')
                    ->constrained('users')
                    ->nullOnDelete();
                $table->index(['clinic_id', 'lifecycle_status'], 'patients_clinic_lifecycle_index');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('patients', 'lifecycle_status')) {
            return;
        }

        Schema::table('patients', function (Blueprint $table): void {
            $table->dropIndex('patients_clinic_lifecycle_index');
            $table->dropConstrainedForeignId('lifecycle_changed_by_user_id');
            $table->dropColumn([
                'lifecycle_status',
                'lifecycle_reason',
                'lifecycle_changed_at',
            ]);
        });
    }
};
