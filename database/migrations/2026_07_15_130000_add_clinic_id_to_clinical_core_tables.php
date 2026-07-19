<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addClinicId('tutors');
        $this->addClinicId('patients');
        $this->addClinicId('schedules');
        $this->addClinicId('appointments');

        $this->backfillFromPatient('schedules');
        $this->backfillFromTutor('schedules');
        $this->backfillFromPatient('appointments');
        $this->backfillFromTutor('appointments');
    }

    public function down(): void
    {
        foreach (['appointments', 'schedules', 'patients', 'tutors'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'clinic_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('clinic_id');
            });
        }
    }

    private function addClinicId(string $table): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'clinic_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $table): void {
            $table->foreignId('clinic_id')
                ->nullable()
                ->after('id')
                ->constrained('clinics')
                ->nullOnDelete();
            $table->index('clinic_id');
        });
    }

    private function backfillFromPatient(string $table): void
    {
        if (
            ! Schema::hasTable($table)
            || ! Schema::hasColumn($table, 'clinic_id')
            || ! Schema::hasColumn($table, 'patient_id')
            || ! Schema::hasColumn('patients', 'clinic_id')
        ) {
            return;
        }

        DB::statement("
            update {$table}
            set clinic_id = (
                select patients.clinic_id
                from patients
                where patients.id = {$table}.patient_id
            )
            where clinic_id is null
              and patient_id is not null
        ");
    }

    private function backfillFromTutor(string $table): void
    {
        if (
            ! Schema::hasTable($table)
            || ! Schema::hasColumn($table, 'clinic_id')
            || ! Schema::hasColumn($table, 'tutor_id')
            || ! Schema::hasColumn('tutors', 'clinic_id')
        ) {
            return;
        }

        DB::statement("
            update {$table}
            set clinic_id = (
                select tutors.clinic_id
                from tutors
                where tutors.id = {$table}.tutor_id
            )
            where clinic_id is null
              and tutor_id is not null
        ");
    }
};
