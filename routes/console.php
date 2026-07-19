<?php

use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Services\ProductIntelligenceAuditService;
use App\Modules\Products\Services\ProductLookupService;
use App\Modules\Products\Services\ProductService;
use App\Modules\ProductIntelligence\Models\GlobalProduct;
use App\Modules\ProductIntelligence\Models\GlobalProductSuggestion;
use App\Modules\ProductIntelligence\Services\ProductIntelligenceService;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

Artisan::command('vetflow:status', function () {
    $this->info('VetFlow pronto para diagnostico.');
})->purpose('Mostra o status basico do VetFlow');

Artisan::command('vetflow:admin:create {--name=} {--email=} {--password=} {--clinic-id=}', function () {
    $name = trim((string) ($this->option('name') ?: $this->ask('Nome do administrador', 'Administrador VetFlow')));
    $email = mb_strtolower(trim((string) ($this->option('email') ?: $this->ask('E-mail do administrador'))));
    $password = (string) ($this->option('password') ?: $this->secret('Senha inicial do administrador'));
    $clinicId = $this->option('clinic-id') !== null && $this->option('clinic-id') !== ''
        ? (int) $this->option('clinic-id')
        : null;

    if ($name === '') {
        $this->error('Informe o nome do administrador.');
        return 1;
    }

    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Informe um e-mail valido.');
        return 1;
    }

    if (mb_strlen($password) < 10) {
        $this->error('A senha inicial deve ter pelo menos 10 caracteres.');
        return 1;
    }

    if ($clinicId !== null && ! Clinic::query()->whereKey($clinicId)->exists()) {
        $this->error("Clinica {$clinicId} nao encontrada.");
        return 1;
    }

    $this->call(AuthorizationSeeder::class);

    DB::transaction(function () use ($name, $email, $password, $clinicId): void {
        $user = User::query()
            ->withTrashed()
            ->firstOrNew(['email' => $email]);

        $user->fill([
            'clinic_id' => $clinicId,
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'active' => true,
        ]);
        $user->save();

        if ($user->trashed()) {
            $user->restore();
        }

        $adminRole = Role::query()
            ->whereNull('clinic_id')
            ->where('slug', 'administrador')
            ->firstOrFail();

        $existingRole = DB::table('user_roles')
            ->where('user_id', $user->id)
            ->where('role_id', $adminRole->id)
            ->first();

        if ($existingRole) {
            DB::table('user_roles')
                ->where('id', $existingRole->id)
                ->update([
                    'deleted_at' => null,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('user_roles')->insert([
                'ulid' => (string) Str::ulid(),
                'user_id' => $user->id,
                'role_id' => $adminRole->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    });

    $this->info("Administrador {$email} criado/atualizado com sucesso.");

    return 0;
})->purpose('Cria ou atualiza o primeiro administrador do VetFlow para implantacao.');

Artisan::command('vetflow:global-products:backfill {--limit=}', function (ProductLookupService $lookupService) {
    $query = Product::query()
        ->where(function ($builder) {
            $builder
                ->whereNotNull('gtin')
                ->orWhereNotNull('barcode');
        })
        ->orderBy('id');

    if ($limit = (int) $this->option('limit')) {
        $query->limit($limit);
    }

    $processed = 0;
    $learned = 0;

    $query->get()->each(function (Product $product) use ($lookupService, &$processed, &$learned) {
        $processed++;

        if ($lookupService->rememberProduct($product, $product->lookup_source ?: 'vetflow_backfill')) {
            $learned++;
        }
    });

    $this->info("Produtos locais analisados: {$processed}");
    $this->info("Produtos enviados ao Catalogo Global: {$learned}");
})->purpose('Reaprende o Catalogo Global a partir dos produtos locais com GTIN/EAN.');

Artisan::command('vetflow:intelligence:diagnose', function () {
    $total = GlobalProduct::query()->count();
    $verified = GlobalProduct::query()->where('status', GlobalProduct::STATUS_VERIFIED)->count();
    $pending = GlobalProduct::query()->where('status', GlobalProduct::STATUS_PENDING)->count();
    $conflict = GlobalProduct::query()->where('status', GlobalProduct::STATUS_CONFLICT)->count();
    $suggestions = GlobalProductSuggestion::query()->where('status', GlobalProduct::STATUS_PENDING)->count();
    $localProducts = Product::query()->count();
    $linkedProducts = Product::query()->whereNotNull('global_product_id')->count();
    $coverage = $localProducts > 0 ? round(($linkedProducts / $localProducts) * 100, 1) : 0;

    $this->info('VetFlow Intelligence');
    $this->line("Catalogo global: {$total}");
    $this->line("Verificados: {$verified}");
    $this->line("Pendentes: {$pending}");
    $this->line("Conflitos: {$conflict}");
    $this->line("Sugestoes pendentes: {$suggestions}");
    $this->line("Cobertura local-global: {$coverage}%");

    if ($conflict > 0 || $suggestions > 0) {
        $this->warn('Status: acao necessaria.');
        return 0;
    }

    if ($pending > 0) {
        $this->warn('Status: em evolucao.');
        return 0;
    }

    $this->info('Status: saudavel.');

    return 0;
})->purpose('Mostra os principais indicadores do VetFlow Intelligence.');

Artisan::command('vetflow:products:diagnose', function (ProductIntelligenceAuditService $auditService) {
    $stats = $auditService->stats();

    $this->info('Diagnostico de produtos locais');
    $this->line("Produtos: {$stats['total']}");
    $this->line("Vinculados ao Catalogo Global: {$stats['linked']}");
    $this->line("Cobertura local-global: {$stats['coverage_percent']}%");
    $this->line("Com EAN sem global: {$stats['unlinked_with_gtin']}");
    $this->line("Sem EAN: {$stats['without_gtin']}");
    $this->line("EAN invalido: {$stats['invalid_gtin']}");
    $this->line("Sem preco de venda: {$stats['without_price']}");
    $this->line("Sem imagem: {$stats['without_image']}");
    $this->line("Estoque baixo: {$stats['low_stock']}");
    $this->line("Global pendente: {$stats['global_pending']}");
    $this->line("Conflito global: {$stats['global_conflict']}");

    if ($stats['global_conflict'] > 0 || $stats['invalid_gtin'] > 0 || $stats['low_stock'] > 0) {
        $this->warn('Status: existem pendencias criticas.');
        return 0;
    }

    if ($stats['unlinked_with_gtin'] > 0 || $stats['without_price'] > 0 || $stats['global_pending'] > 0) {
        $this->warn('Status: existem pontos de atencao.');
        return 0;
    }

    $this->info('Status: produtos locais saudaveis.');

    return 0;
})->purpose('Mostra pendencias dos produtos locais e vinculos com o Catalogo Global.');

Artisan::command('vetflow:products:link-global {--limit=}', function (ProductService $productService) {
    $limit = $this->option('limit') !== null && $this->option('limit') !== ''
        ? max(0, (int) $this->option('limit'))
        : null;

    if ($limit === 0) {
        $this->info('Nenhum produto processado porque o limite informado foi 0.');
        return 0;
    }

    $result = $productService->autoLinkLocalProducts($limit);

    $this->info("Produtos analisados: {$result['processed']}");
    $this->line("Vinculados: {$result['linked']}");
    $this->line("Ignorados: {$result['skipped']}");

    return 0;
})->purpose('Vincula produtos locais com EAN ao Catalogo Global VetFlow.');

Artisan::command('vetflow:intelligence:enrich-pending {--limit=10}', function (ProductIntelligenceService $intelligenceService) {
    $limit = max(0, (int) $this->option('limit'));

    if ($limit === 0) {
        $this->info('Nenhum produto global processado porque o limite informado foi 0.');
        return 0;
    }

    $products = GlobalProduct::query()
        ->where('status', GlobalProduct::STATUS_PENDING)
        ->orderByRaw('last_lookup_at IS NOT NULL')
        ->oldest('last_lookup_at')
        ->limit($limit)
        ->get();

    $processed = 0;
    $enriched = 0;

    foreach ($products as $product) {
        $processed++;

        if ($intelligenceService->enrichGlobalProduct($product)) {
            $enriched++;
        }
    }

    $this->info("Produtos globais pendentes analisados: {$processed}");
    $this->line("Enriquecidos: {$enriched}");

    return 0;
})->purpose('Tenta enriquecer produtos pendentes do Catalogo Global.');
