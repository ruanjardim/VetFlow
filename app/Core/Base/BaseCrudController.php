<?php

namespace App\Core\Base;

use Illuminate\Http\Request;

abstract class BaseCrudController
{
    protected BaseService $service;

    protected string $viewPath;

    protected string $routeName;

    protected string $viewVariable = 'items';

    public function index()
    {
        return view("{$this->viewPath}.index", [
            $this->viewVariable => $this->service->paginate(),
        ]);
    }

    public function create()
    {
        return view("{$this->viewPath}.create");
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request, $this->storeRequest());

        $this->service->create($validated);

        return redirect()
            ->route("{$this->routeName}.index")
            ->with('success', 'Registro criado com sucesso.');
    }

    public function edit(int $id)
    {
        return view("{$this->viewPath}.edit", [
            'item' => $this->service->findOrFail($id),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $validated = $this->validateRequest($request, $this->updateRequest());

        $this->service->update($id, $validated);

        return redirect()
            ->route("{$this->routeName}.index")
            ->with('success', 'Registro atualizado com sucesso.');
    }

    public function destroy(int $id)
    {
        $this->service->delete($id);

        return redirect()
            ->route("{$this->routeName}.index")
            ->with('success', 'Registro removido com sucesso.');
    }

    protected function validateRequest(Request $request, string $requestClass): array
    {
        $formRequest = app($requestClass);

        $formRequest->setContainer(app())
            ->setRedirector(app('redirect'))
            ->initialize(
                $request->query->all(),
                $request->request->all(),
                $request->attributes->all(),
                $request->cookies->all(),
                $request->files->all(),
                $request->server->all(),
                $request->getContent()
            );

        $formRequest->setUserResolver($request->getUserResolver());
        $formRequest->setRouteResolver($request->getRouteResolver());

        return $formRequest->validateResolved() ?: $formRequest->validated();
    }

    protected function storeRequest(): string
    {
        return Request::class;
    }

    protected function updateRequest(): string
    {
        return Request::class;
    }
}