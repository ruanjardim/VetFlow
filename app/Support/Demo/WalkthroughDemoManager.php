<?php

namespace App\Support\Demo;

use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use Illuminate\Support\Facades\DB;

class WalkthroughDemoManager
{
    /**
     * @return array<string, int>
     */
    public function cleanup(): array
    {
        $clinic = $this->clinic();

        if ($clinic === null) {
            return $this->emptySummary();
        }

        return DB::transaction(function () use ($clinic): array {
            $summary = $this->emptySummary();

            $summary['implementation_pilot_decisions'] = DB::table('implementation_pilot_decisions')
                ->where('clinic_id', $clinic->id)
                ->where('notes', WalkthroughDemoFixture::IMPLEMENTATION_NOTE)
                ->delete();
            $summary['implementation_pilot_releases'] = DB::table('implementation_pilot_releases')
                ->where('clinic_id', $clinic->id)
                ->where('release_notes', WalkthroughDemoFixture::PILOT_RELEASE_NOTES)
                ->delete();
            $summary['implementation_pilot_checks'] = DB::table('implementation_pilot_checks')
                ->where('clinic_id', $clinic->id)
                ->where('notes', WalkthroughDemoFixture::IMPLEMENTATION_NOTE)
                ->delete();
            $summary['implementation_imports'] = DB::table('implementation_imports')
                ->where('clinic_id', $clinic->id)
                ->whereIn('file_name', array_values(WalkthroughDemoFixture::IMPLEMENTATION_IMPORT_FILES))
                ->delete();
            $saleIds = DB::table('sales')
                ->where('clinic_id', $clinic->id)
                ->where('code', WalkthroughDemoFixture::SALE_CODE)
                ->pluck('id');

            if ($saleIds->isNotEmpty()) {
                $summary['sale_events'] = DB::table('sale_events')
                    ->whereIn('sale_id', $saleIds)
                    ->delete();
                $summary['sale_items'] = DB::table('sale_items')
                    ->whereIn('sale_id', $saleIds)
                    ->delete();
                $summary['sale_payments'] = DB::table('sale_payments')
                    ->whereIn('sale_id', $saleIds)
                    ->delete();
                $summary['sales'] = DB::table('sales')
                    ->whereIn('id', $saleIds)
                    ->delete();
            }

            $summary['financial_transactions'] = DB::table('financial_transactions')
                ->where('clinic_id', $clinic->id)
                ->whereIn('reference', WalkthroughDemoFixture::FINANCIAL_REFERENCES)
                ->delete();

            $summary['inventory_movements'] = DB::table('inventory_movements')
                ->where('clinic_id', $clinic->id)
                ->where('source', WalkthroughDemoFixture::SOURCE)
                ->delete();

            $productIds = DB::table('products')
                ->where('clinic_id', $clinic->id)
                ->whereIn('sku', WalkthroughDemoFixture::PRODUCT_SKUS)
                ->pluck('id');

            if ($productIds->isNotEmpty()) {
                $summary['products'] = DB::table('products')
                    ->whereIn('id', $productIds)
                    ->delete();
            }

            $summary['appointments'] = DB::table('appointments')
                ->where('clinic_id', $clinic->id)
                ->where('title', WalkthroughDemoFixture::APPOINTMENT_TITLE)
                ->delete();

            $patientIds = DB::table('patients')
                ->where('clinic_id', $clinic->id)
                ->where('name', WalkthroughDemoFixture::PATIENT_NAME)
                ->pluck('id');

            if ($patientIds->isNotEmpty()) {
                $summary['patients'] = DB::table('patients')
                    ->whereIn('id', $patientIds)
                    ->delete();
            }

            $summary['tutors'] = DB::table('tutors')
                ->where('clinic_id', $clinic->id)
                ->where('email', WalkthroughDemoFixture::TUTOR_EMAIL)
                ->delete();

            $summary['suppliers'] = DB::table('suppliers')
                ->where('clinic_id', $clinic->id)
                ->where('document', WalkthroughDemoFixture::SUPPLIER_DOCUMENT)
                ->delete();

            $demoUser = User::query()
                ->withTrashed()
                ->where('clinic_id', $clinic->id)
                ->where('email', WalkthroughDemoFixture::USER_EMAIL)
                ->first();

            if ($demoUser !== null) {
                DB::table('sessions')
                    ->where('user_id', $demoUser->id)
                    ->delete();

                if (! $demoUser->trashed()) {
                    $demoUser->delete();
                    $summary['users'] = 1;
                }
            }

            $globalProductIds = DB::table('global_products')
                ->whereIn('gtin', WalkthroughDemoFixture::GLOBAL_PRODUCT_GTINS)
                ->pluck('id');

            if ($globalProductIds->isNotEmpty()) {
                $summary['clinic_products'] = DB::table('clinic_products')
                    ->where('clinic_id', $clinic->id)
                    ->whereIn('global_product_id', $globalProductIds)
                    ->delete();
                $summary['global_product_sources'] = DB::table('global_product_sources')
                    ->whereIn('global_product_id', $globalProductIds)
                    ->where('source_name', WalkthroughDemoFixture::SOURCE)
                    ->delete();
            }

            $summary['global_product_suggestions'] = DB::table('global_product_suggestions')
                ->whereIn('gtin', WalkthroughDemoFixture::GLOBAL_PRODUCT_GTINS)
                ->where('source_name', WalkthroughDemoFixture::SOURCE)
                ->delete();

            if ($globalProductIds->isNotEmpty()) {
                $summary['global_products'] = DB::table('global_products')
                    ->whereIn('id', $globalProductIds)
                    ->delete();
            }

            return $summary;
        });
    }

    public function clinic(): ?Clinic
    {
        return Clinic::query()
            ->withTrashed()
            ->where('cnpj', WalkthroughDemoFixture::CLINIC_CNPJ)
            ->first();
    }

    /**
     * @return array<string, int>
     */
    private function emptySummary(): array
    {
        return [
            'implementation_pilot_decisions' => 0,
            'implementation_pilot_releases' => 0,
            'implementation_pilot_checks' => 0,
            'implementation_imports' => 0,
            'users' => 0,
            'tutors' => 0,
            'suppliers' => 0,
            'patients' => 0,
            'appointments' => 0,
            'products' => 0,
            'inventory_movements' => 0,
            'sales' => 0,
            'sale_items' => 0,
            'sale_payments' => 0,
            'sale_events' => 0,
            'financial_transactions' => 0,
            'clinic_products' => 0,
            'global_products' => 0,
            'global_product_sources' => 0,
            'global_product_suggestions' => 0,
        ];
    }
}
