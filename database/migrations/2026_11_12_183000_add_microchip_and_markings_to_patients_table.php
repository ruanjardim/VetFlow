<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $addMicrochip = ! Schema::hasColumn('patients', 'microchip');
        $addMarkings = ! Schema::hasColumn('patients', 'markings');

        if (! $addMicrochip && ! $addMarkings) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) use ($addMicrochip, $addMarkings): void {
            if ($addMicrochip) {
                $table->string('microchip', 100)->nullable()->after('photo_path');
            }

            if ($addMarkings) {
                $table->text('markings')->nullable()->after('coat');
            }
        });
    }

    public function down(): void
    {
        $columns = array_values(array_filter([
            Schema::hasColumn('patients', 'microchip') ? 'microchip' : null,
            Schema::hasColumn('patients', 'markings') ? 'markings' : null,
        ]));

        if ($columns === []) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }
};
