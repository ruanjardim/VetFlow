<?php

namespace App\Modules\ServiceOrders\Requests;

use App\Modules\ServiceOrders\Models\ServiceOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(array_keys(ServiceOrder::STATUS_LABELS))],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Informe o novo status da comanda.',
            'status.in' => 'Informe um status válido para a comanda.',
        ];
    }
}
