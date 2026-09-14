<?php

namespace Database\Seeders;

use App\Modules\Saas\Models\Feature;
use App\Modules\Saas\Models\Plan;
use App\Modules\Saas\Models\PlanFeature;
use App\Modules\Saas\Support\FeatureCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SaasPlanSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $features = collect(FeatureCatalog::definitions())->map(function (array $definition, string $key): Feature {
                return Feature::query()->updateOrCreate(
                    ['key' => $key],
                    [
                        'name' => $definition['name'],
                        'description' => $definition['description'],
                        'type' => $definition['type'],
                        'default_value' => $definition['type'] === 'boolean' ? false : null,
                        'display_order' => array_search($key, array_keys(FeatureCatalog::definitions()), true),
                    ]
                );
            });

            $plans = [
                'essencial' => [
                    'name' => 'Essencial', 'description' => 'Operação enxuta com cadastros, PDV, produtos e estoque.',
                    'monthly_price' => 99, 'annual_price' => 990, 'max_users' => 2, 'max_units' => 1, 'display_order' => 10,
                    'features' => ['dashboard', 'clients', 'pets', 'pdv', 'products', 'inventory'],
                ],
                'profissional' => [
                    'name' => 'Profissional', 'description' => 'Operação integrada com agenda, serviços, compras e financeiro.',
                    'monthly_price' => 199, 'annual_price' => 1990, 'max_users' => 5, 'max_units' => 1, 'display_order' => 20,
                    'features' => ['dashboard', 'clients', 'pets', 'agenda', 'petshop_services', 'pdv', 'products', 'inventory', 'purchases', 'suppliers', 'financial'],
                ],
                'completo' => [
                    'name' => 'Completo', 'description' => 'Todos os módulos do VetFlow para operações completas e multiunidade.',
                    'monthly_price' => 349, 'annual_price' => 3490, 'max_users' => 15, 'max_units' => 3, 'display_order' => 30,
                    'features' => ['dashboard', 'clients', 'pets', 'agenda', 'petshop_services', 'veterinary', 'pdv', 'products', 'inventory', 'purchases', 'suppliers', 'financial', 'commissions'],
                ],
            ];

            foreach ($plans as $slug => $definition) {
                $included = $definition['features'];
                unset($definition['features']);
                $plan = Plan::query()->firstOrCreate(['slug' => $slug], $definition + ['active' => true, 'internal' => false]);

                if (! $plan->wasRecentlyCreated) {
                    continue;
                }

                $features->where('type', 'boolean')->each(function (Feature $feature) use ($plan, $included): void {
                    PlanFeature::query()->create([
                        'plan_id' => $plan->id,
                        'feature_id' => $feature->id,
                        'value' => in_array($feature->key, $included, true),
                    ]);
                });
            }
        });
    }
}
