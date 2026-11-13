<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $addHasPedigree = ! Schema::hasColumn('patients', 'has_pedigree');
        $addPedigreeNumber = ! Schema::hasColumn('patients', 'pedigree_number');

        if (! $addHasPedigree && ! $addPedigreeNumber) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) use ($addHasPedigree, $addPedigreeNumber): void {
            if ($addHasPedigree) {
                $table->boolean('has_pedigree')->nullable()->after('microchip');
            }

            if ($addPedigreeNumber) {
                $table->string('pedigree_number', 120)->nullable()->after('has_pedigree');
            }
        });
    }

    public function down(): void
    {
        $columns = array_values(array_filter([
            Schema::hasColumn('patients', 'has_pedigree') ? 'has_pedigree' : null,
            Schema::hasColumn('patients', 'pedigree_number') ? 'pedigree_number' : null,
        ]));

        if ($columns === []) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }
};
