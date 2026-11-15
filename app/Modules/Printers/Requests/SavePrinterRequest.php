<?php

namespace App\Modules\Printers\Requests;

use App\Core\Base\BaseRequest;
use App\Modules\Printers\Services\PrinterService;
use Illuminate\Validation\Rule;

class SavePrinterRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'clinic_id' => [
                Rule::requiredIf(fn (): bool => auth()->user()?->clinic_id === null),
                'nullable',
                'integer',
                Rule::exists('clinics', 'id')->where(fn ($query) => $query->where('active', true)),
            ],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(array_keys(PrinterService::types()))],
            'purpose' => ['required', Rule::in(array_keys(PrinterService::purposes()))],
            'connection_type' => ['required', Rule::in(array_keys(PrinterService::connections()))],
            'paper_size' => ['required', Rule::in(array_keys(PrinterService::paperSizes()))],
            'queue_name' => ['nullable', 'string', 'max:160'],
            'network_host' => ['nullable', 'string', 'max:255', 'required_if:connection_type,network'],
            'network_port' => ['nullable', 'integer', 'between:1,65535'],
            'manufacturer' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['required', 'boolean'],
            'active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Informe um nome para identificar a impressora.',
            'name.max' => 'O nome da impressora deve ter no máximo 120 caracteres.',
            'type.required' => 'Selecione o tipo da impressora.',
            'type.in' => 'Selecione um tipo de impressora válido.',
            'purpose.required' => 'Selecione a finalidade principal.',
            'purpose.in' => 'Selecione uma finalidade válida.',
            'connection_type.required' => 'Selecione a forma de conexão.',
            'connection_type.in' => 'Selecione uma forma de conexão válida.',
            'paper_size.required' => 'Selecione o tamanho do papel.',
            'paper_size.in' => 'Selecione um tamanho de papel válido.',
            'network_host.required_if' => 'Informe o endereço IP ou host da impressora de rede.',
            'network_port.between' => 'A porta de rede deve estar entre 1 e 65535.',
            'notes.max' => 'As observações devem ter no máximo 1000 caracteres.',
        ];
    }
}
