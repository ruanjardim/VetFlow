<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table): void {
            $table->string('business_type', 40)->nullable()->after('trade_name')->index();
        });

        Schema::create('saas_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->decimal('monthly_price', 12, 2)->nullable();
            $table->decimal('annual_price', 12, 2)->nullable();
            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedInteger('max_units')->nullable();
            $table->unsignedInteger('display_order')->default(0)->index();
            $table->boolean('internal')->default(false)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('saas_features', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type', 20);
            $table->json('default_value')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('saas_plan_features', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained('saas_plans')->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained('saas_features')->cascadeOnDelete();
            $table->json('value')->nullable();
            $table->timestamps();
            $table->unique(['plan_id', 'feature_id']);
        });

        Schema::create('saas_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->unique()->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('saas_plans')->restrictOnDelete();
            $table->string('status', 20)->default('active')->index();
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->date('trial_ends_at')->nullable();
            $table->date('renews_at')->nullable();
            $table->timestamps();
        });

        Schema::create('saas_subscription_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_id')->constrained('saas_subscriptions')->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained('saas_features')->cascadeOnDelete();
            $table->json('value')->nullable();
            $table->timestamps();
            $table->unique(['subscription_id', 'feature_id']);
        });

        $now = now();
        $planId = DB::table('saas_plans')->insertGetId([
            'name' => 'Legado interno',
            'slug' => 'legacy-internal',
            'description' => 'Compatibilidade para estabelecimentos anteriores à gestão comercial de planos.',
            'active' => true,
            'max_users' => null,
            'max_units' => null,
            'display_order' => 999,
            'internal' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $features = [
            ['dashboard', 'Dashboard', 'boolean', false],
            ['clients', 'Clientes e responsáveis', 'boolean', false],
            ['pets', 'Pets e pacientes', 'boolean', false],
            ['agenda', 'Agenda e consultas', 'boolean', false],
            ['petshop_services', 'Banho, tosa e serviços', 'boolean', false],
            ['veterinary', 'Módulo veterinário', 'boolean', false],
            ['pdv', 'PDV e vendas', 'boolean', false],
            ['products', 'Produtos e catálogo', 'boolean', false],
            ['inventory', 'Estoque', 'boolean', false],
            ['purchases', 'Compras e entradas', 'boolean', false],
            ['suppliers', 'Fornecedores', 'boolean', false],
            ['financial', 'Financeiro', 'boolean', false],
            ['commissions', 'Comissões', 'boolean', false],
            ['max_users', 'Limite de usuários ativos', 'numeric', null],
            ['max_units', 'Limite de unidades', 'numeric', null],
        ];

        foreach ($features as $order => [$key, $name, $type, $default]) {
            $featureId = DB::table('saas_features')->insertGetId([
                'key' => $key,
                'name' => $name,
                'description' => null,
                'type' => $type,
                'default_value' => json_encode($default),
                'display_order' => $order,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($type === 'boolean') {
                DB::table('saas_plan_features')->insert([
                    'plan_id' => $planId,
                    'feature_id' => $featureId,
                    'value' => json_encode(true),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        DB::table('clinics')->whereNull('deleted_at')->orderBy('id')->get()->each(function ($clinic) use ($planId, $now): void {
            DB::table('saas_subscriptions')->insert([
                'clinic_id' => $clinic->id,
                'plan_id' => $planId,
                'status' => 'active',
                'starts_at' => $now->toDateString(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_subscription_overrides');
        Schema::dropIfExists('saas_subscriptions');
        Schema::dropIfExists('saas_plan_features');
        Schema::dropIfExists('saas_features');
        Schema::dropIfExists('saas_plans');

        Schema::table('clinics', function (Blueprint $table): void {
            $table->dropColumn('business_type');
        });
    }
};
