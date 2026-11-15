<?php

namespace App\Modules\Commissions\Repositories;

use App\Core\Base\BaseRepository;
use App\Modules\Commissions\Contracts\CommissionRuleRepositoryInterface;
use App\Modules\Commissions\Models\CommissionRule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class CommissionRuleRepository extends BaseRepository implements CommissionRuleRepositoryInterface
{
    public function __construct(CommissionRule $commissionRule)
    {
        $this->model = $commissionRule;
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->with('seller.clinic')
            ->orderByDesc('active')
            ->orderBy('seller_user_id')
            ->orderByDesc('starts_on')
            ->paginate($perPage);
    }

    public function findOrFail(int $id): Model
    {
        return $this->query()->with('seller.clinic')->findOrFail($id);
    }
}
