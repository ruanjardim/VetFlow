<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Sales\Models\CashSession;
use App\Modules\Sales\Services\CashSessionService;

/**
 * Receiving money requires an open cash session of the operator in the sale
 * clinic; tests that finish sales with payments open one first.
 */
trait OpensCashSessions
{
    protected function openCashSession(User $user, Clinic|int|null $clinic = null, float $openingAmount = 0): CashSession
    {
        $clinicId = $clinic instanceof Clinic ? $clinic->id : ($clinic ?? $user->clinic_id);

        return app(CashSessionService::class)->open($user, (int) $clinicId, $openingAmount);
    }
}
