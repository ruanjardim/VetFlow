<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $addPhotoPath = ! Schema::hasColumn('patients', 'photo_path');
        $addNeutered = ! Schema::hasColumn('patients', 'neutered');

        if (! $addPhotoPath && ! $addNeutered) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) use ($addPhotoPath, $addNeutered): void {
            if ($addPhotoPath) {
                $table->string('photo_path')->nullable()->after('name');
            }

            if ($addNeutered) {
                $table->boolean('neutered')->nullable()->after('gender');
            }
        });
    }

    public function down(): void
    {
        $columns = array_values(array_filter([
            Schema::hasColumn('patients', 'photo_path') ? 'photo_path' : null,
            Schema::hasColumn('patients', 'neutered') ? 'neutered' : null,
        ]));

        if ($columns === []) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }
};
