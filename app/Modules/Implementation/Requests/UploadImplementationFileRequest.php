<?php

namespace App\Modules\Implementation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use LogicException;

class UploadImplementationFileRequest extends FormRequest
{
    private const DEFINITIONS = [
        'implementation.tutors.upload' => [
            'input' => 'tutors_file',
            'label' => 'tutores',
        ],
        'implementation.patients.upload' => [
            'input' => 'patients_file',
            'label' => 'pacientes',
        ],
        'implementation.suppliers.upload' => [
            'input' => 'suppliers_file',
            'label' => 'fornecedores',
        ],
        'implementation.products.upload' => [
            'input' => 'products_file',
            'label' => 'produtos',
        ],
        'implementation.stock.upload' => [
            'input' => 'stock_file',
            'label' => 'estoque',
        ],
        'implementation.financial.upload' => [
            'input' => 'financial_file',
            'label' => 'financeiro',
        ],
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $input = $this->definition()['input'];

        return [
            $input => [
                'required',
                'file',
                'extensions:csv,xlsx',
                'max:2048',
            ],
        ];
    }

    public function messages(): array
    {
        $definition = $this->definition();
        $input = $definition['input'];
        $label = $definition['label'];

        return [
            "{$input}.required" => "Selecione o arquivo de {$label}.",
            "{$input}.file" => "O arquivo de {$label} enviado não é válido.",
            "{$input}.extensions" => 'Envie um arquivo com a extensão .csv ou .xlsx.',
            "{$input}.max" => 'O arquivo deve ter no máximo 2 MB.',
        ];
    }

    /**
     * @return array{input: string, label: string}
     */
    private function definition(): array
    {
        $routeName = $this->route()?->getName();
        $definition = is_string($routeName)
            ? self::DEFINITIONS[$routeName] ?? null
            : null;

        if (! is_array($definition)) {
            throw new LogicException('A rota de upload da implantação não foi configurada.');
        }

        return $definition;
    }
}
