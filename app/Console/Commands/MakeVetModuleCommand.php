<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeVetModuleCommand extends Command
{
    protected $signature = 'make:vet-module {name}';

    protected $description = 'Cria um módulo padrão do VetFlow.';

    public function handle(): int
    {
        $module = Str::studly($this->argument('name'));
        $singular = Str::singular($module);
        $plural = Str::plural($module);

        $pluralVariable = Str::camel($plural);
        $routeName = Str::kebab($plural);
        $viewPath = Str::kebab($plural);

        $basePath = app_path("Modules/{$module}");

        $folders = [
            "{$basePath}/Controllers",
            "{$basePath}/Contracts",
            "{$basePath}/Models",
            "{$basePath}/Repositories",
            "{$basePath}/Requests",
            "{$basePath}/Services",
            "{$basePath}/Routes",
            "{$basePath}/Providers",
            "{$basePath}/Policies",
            resource_path("views/{$viewPath}"),
        ];

        foreach ($folders as $folder) {
            File::ensureDirectoryExists($folder);
        }

        $this->createFile("{$basePath}/Controllers/{$singular}Controller.php", $this->controller($module, $singular, $viewPath, $routeName, $pluralVariable));
        $this->createFile("{$basePath}/Contracts/{$singular}RepositoryInterface.php", $this->contract($module, $singular));
        $this->createFile("{$basePath}/Models/{$singular}.php", $this->model($module, $singular));
        $this->createFile("{$basePath}/Repositories/{$singular}Repository.php", $this->repository($module, $singular));
        $this->createFile("{$basePath}/Services/{$singular}Service.php", $this->service($module, $singular));
        $this->createFile("{$basePath}/Providers/{$singular}ServiceProvider.php", $this->provider($module, $singular));
        $this->createFile("{$basePath}/Requests/Store{$singular}Request.php", $this->request($module, $singular, 'Store'));
        $this->createFile("{$basePath}/Requests/Update{$singular}Request.php", $this->request($module, $singular, 'Update'));
        $this->createFile("{$basePath}/Routes/web.php", $this->routes($module, $singular, $routeName));

        $this->createFile(resource_path("views/{$viewPath}/index.blade.php"), $this->indexView($pluralVariable, $routeName));
        $this->createFile(resource_path("views/{$viewPath}/create.blade.php"), $this->createView($routeName));
        $this->createFile(resource_path("views/{$viewPath}/edit.blade.php"), $this->editView($routeName));

        $this->info("Módulo {$module} criado com sucesso.");
        $this->warn("Não esqueça de registrar a rota em routes/web.php e o provider em bootstrap/providers.php.");

        return self::SUCCESS;
    }

    private function createFile(string $path, string $content): void
    {
        if (File::exists($path)) {
            $this->warn("Já existe: {$path}");
            return;
        }

        File::put($path, $content);
        $this->line("Criado: {$path}");
    }

    private function controller(string $module, string $singular, string $viewPath, string $routeName, string $pluralVariable): string
    {
        return <<<PHP
<?php

namespace App\\Modules\\{$module}\\Controllers;

use App\\Core\\Base\\BaseCrudController;
use App\\Modules\\{$module}\\Services\\{$singular}Service;

class {$singular}Controller extends BaseCrudController
{
    public function __construct({$singular}Service \$service)
    {
        \$this->service = \$service;
        \$this->viewPath = '{$viewPath}';
        \$this->routeName = '{$routeName}';
        \$this->viewVariable = '{$pluralVariable}';
    }
}

PHP;
    }

    private function contract(string $module, string $singular): string
    {
        return <<<PHP
<?php

namespace App\\Modules\\{$module}\\Contracts;

interface {$singular}RepositoryInterface
{
}

PHP;
    }

    private function model(string $module, string $singular): string
    {
        $table = Str::snake(Str::plural($singular));

        return <<<PHP
<?php

namespace App\\Modules\\{$module}\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\SoftDeletes;

class {$singular} extends Model
{
    use SoftDeletes;

    protected \$table = '{$table}';

    protected \$guarded = [];
}

PHP;
    }

    private function repository(string $module, string $singular): string
    {
        return <<<PHP
<?php

namespace App\\Modules\\{$module}\\Repositories;

use App\\Core\\Base\\BaseRepository;
use App\\Modules\\{$module}\\Contracts\\{$singular}RepositoryInterface;
use App\\Modules\\{$module}\\Models\\{$singular};

class {$singular}Repository extends BaseRepository implements {$singular}RepositoryInterface
{
    public function __construct({$singular} \$model)
    {
        \$this->model = \$model;
    }
}

PHP;
    }

    private function service(string $module, string $singular): string
    {
        return <<<PHP
<?php

namespace App\\Modules\\{$module}\\Services;

use App\\Core\\Base\\BaseService;
use App\\Modules\\{$module}\\Contracts\\{$singular}RepositoryInterface;

class {$singular}Service extends BaseService
{
    public function __construct({$singular}RepositoryInterface \$repository)
    {
        \$this->repository = \$repository;
    }
}

PHP;
    }

    private function provider(string $module, string $singular): string
    {
        return <<<PHP
<?php

namespace App\\Modules\\{$module}\\Providers;

use Illuminate\\Support\\ServiceProvider;
use App\\Modules\\{$module}\\Contracts\\{$singular}RepositoryInterface;
use App\\Modules\\{$module}\\Repositories\\{$singular}Repository;

class {$singular}ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        \$this->app->bind(
            {$singular}RepositoryInterface::class,
            {$singular}Repository::class
        );
    }

    public function boot(): void
    {
        //
    }
}

PHP;
    }

    private function request(string $module, string $singular, string $type): string
    {
        return <<<PHP
<?php

namespace App\\Modules\\{$module}\\Requests;

use Illuminate\\Foundation\\Http\\FormRequest;

class {$type}{$singular}Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}

PHP;
    }

    private function routes(string $module, string $singular, string $routeName): string
    {
        return <<<PHP
<?php

use Illuminate\\Support\\Facades\\Route;
use App\\Modules\\{$module}\\Controllers\\{$singular}Controller;

Route::resource('{$routeName}', {$singular}Controller::class)
    ->names('{$routeName}');

PHP;
    }

    private function indexView(string $pluralVariable, string $routeName): string
    {
        return <<<BLADE
@extends('layouts.admin')

@section('title', 'Listagem')

@section('content')
<div class="card">
    <h1>Listagem</h1>

    <a href="{{ route('{$routeName}.create') }}">Novo registro</a>

    <p>Total: {{ \${$pluralVariable}->total() }}</p>

    <table width="100%" border="1" cellpadding="10">
        <thead>
            <tr>
                <th>ID</th>
                <th>Criado em</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            @forelse(\${$pluralVariable} as \$item)
                <tr>
                    <td>{{ \$item->id }}</td>
                    <td>{{ \$item->created_at }}</td>
                    <td>
                        <a href="{{ route('{$routeName}.edit', \$item->id) }}">Editar</a>

                        <form action="{{ route('{$routeName}.destroy', \$item->id) }}" method="POST" style="display:inline;">
                            @csrf
                            @method('DELETE')

                            <button type="submit" onclick="return confirm('Deseja excluir este registro?')">
                                Excluir
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">Nenhum registro encontrado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{ \${$pluralVariable}->links() }}
</div>
@endsection

BLADE;
    }

    private function createView(string $routeName): string
    {
        return <<<BLADE
@extends('layouts.admin')

@section('title', 'Novo registro')

@section('content')
<div class="card">
    <h1>Novo registro</h1>

    <form action="{{ route('{$routeName}.store') }}" method="POST">
        @csrf

        <button type="submit">Salvar</button>
    </form>
</div>
@endsection

BLADE;
    }

    private function editView(string $routeName): string
    {
        return <<<BLADE
@extends('layouts.admin')

@section('title', 'Editar registro')

@section('content')
<div class="card">
    <h1>Editar registro</h1>

    <form action="{{ route('{$routeName}.update', \$item->id) }}" method="POST">
        @csrf
        @method('PUT')

        <button type="submit">Atualizar</button>
    </form>
</div>
@endsection

BLADE;
    }
}