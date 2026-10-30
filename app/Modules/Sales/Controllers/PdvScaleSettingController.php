<?php

namespace App\Modules\Sales\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Services\AuditTrailService;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Sales\Models\PdvScaleSetting;
use App\Modules\Sales\Requests\UpdatePdvScaleSettingRequest;
use App\Modules\Sales\Services\PdvScaleLabelService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PdvScaleSettingController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly PdvScaleLabelService $labels,
        private readonly AuditTrailService $audit
    ) {}

    public function edit(Request $request): View
    {
        $clinic = $this->clinic($request, $request->integer('clinic_id'));
        $setting = PdvScaleSetting::query()->firstOrNew(
            ['clinic_id' => $clinic->id],
            [
                'enabled' => false,
                'label_prefix' => '20',
                'product_code_digits' => 5,
                'weight_decimals' => 3,
            ]
        );

        return view('sales.pdv-scale-settings', [
            'clinic' => $clinic,
            'clinics' => $this->clinics(),
            'setting' => $setting,
            'requiresClinic' => $this->tenant->isGlobal(),
            'exampleLabel' => $this->labels->exampleLabel($setting),
            'valueDigits' => $setting->valueDigits(),
        ]);
    }

    public function update(UpdatePdvScaleSettingRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $clinic = $this->clinic($request, (int) $data['clinic_id']);
        $setting = PdvScaleSetting::query()->firstOrNew(['clinic_id' => $clinic->id]);
        $before = $setting->exists ? $setting->only([
            'enabled', 'label_prefix', 'product_code_digits', 'weight_decimals',
        ]) : [];

        $setting->fill([
            'clinic_id' => $clinic->id,
            'enabled' => (bool) $data['enabled'],
            'label_prefix' => $data['label_prefix'],
            'product_code_digits' => (int) $data['product_code_digits'],
            'weight_decimals' => (int) $data['weight_decimals'],
            'updated_by' => $request->user()?->id,
        ])->save();

        $this->audit->record(
            'pdv.scale-settings.updated',
            $setting,
            $before,
            $setting->only(['enabled', 'label_prefix', 'product_code_digits', 'weight_decimals']),
            $request->user(),
            clinicId: $clinic->id,
            subjectLabel: $clinic->trade_name ?: $clinic->corporate_name
        );

        return redirect()
            ->route('sales.pdv-scale-settings.edit', $this->tenant->isGlobal() ? ['clinic_id' => $clinic->id] : [])
            ->with('success', $setting->enabled
                ? 'Leitura de etiquetas de peso ativada para esta clínica.'
                : 'Leitura de etiquetas de peso desativada para esta clínica.');
    }

    private function clinic(Request $request, ?int $clinicId): Clinic
    {
        $resolved = $request->user()?->clinic_id ?? $clinicId;

        if (! $resolved && $this->tenant->isGlobal()) {
            $resolved = Clinic::query()->active()->orderBy('trade_name')->value('id');
        }

        abort_if(! $resolved, 404, 'Nenhum estabelecimento ativo disponível.');

        return Clinic::query()->active()->findOrFail((int) $resolved);
    }

    private function clinics()
    {
        return $this->tenant->isGlobal()
            ? Clinic::query()->active()->orderBy('trade_name')->orderBy('corporate_name')->get()
            : collect();
    }
}
