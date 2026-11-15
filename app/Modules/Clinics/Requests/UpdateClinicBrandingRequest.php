<?php

namespace App\Modules\Clinics\Requests;

use App\Core\Base\BaseRequest;
use App\Modules\Clinics\Services\ClinicBrandingService;
use Illuminate\Validation\Rule;

class UpdateClinicBrandingRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'brand_icon_mode' => ['required', Rule::in(array_keys(ClinicBrandingService::modes()))],
            'brand_icon_key' => ['required', Rule::in(array_keys(ClinicBrandingService::icons()))],
        ];
    }

    public function messages(): array
    {
        return [
            'brand_icon_mode.required' => 'Escolha como o ícone da clínica será exibido.',
            'brand_icon_mode.in' => 'Escolha um modo de exibição válido.',
            'brand_icon_key.required' => 'Escolha um ícone para a identidade da clínica.',
            'brand_icon_key.in' => 'Escolha um ícone válido.',
        ];
    }
}
