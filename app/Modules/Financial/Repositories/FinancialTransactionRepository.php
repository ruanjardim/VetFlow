<?php

namespace App\Modules\Financial\Repositories;

use App\Core\Base\BaseRepository;
use App\Modules\Financial\Contracts\FinancialTransactionRepositoryInterface;
use App\Modules\Financial\Models\FinancialTransaction;

class FinancialTransactionRepository extends BaseRepository implements FinancialTransactionRepositoryInterface
{
    public function __construct(FinancialTransaction $financialTransaction)
    {
        $this->model = $financialTransaction;
    }
}
