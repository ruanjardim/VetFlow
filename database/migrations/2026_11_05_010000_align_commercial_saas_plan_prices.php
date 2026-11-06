<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->updatePlan('essencial', 97, 970, 3);
        $this->updatePlan('profissional', 197, 1970, 6);
        $this->updatePlan('completo', 347, 3470, 15);
    }

    public function down(): void
    {
        $this->updatePlan('essencial', 99, 990, 2);
        $this->updatePlan('profissional', 199, 1990, 5);
        $this->updatePlan('completo', 349, 3490, 15);
    }

    private function updatePlan(string $slug, int $monthlyPrice, int $annualPrice, int $maxUsers): void
    {
        DB::table('saas_plans')
            ->where('slug', $slug)
            ->update([
                'monthly_price' => $monthlyPrice,
                'annual_price' => $annualPrice,
                'max_users' => $maxUsers,
                'updated_at' => now(),
            ]);
    }
};
