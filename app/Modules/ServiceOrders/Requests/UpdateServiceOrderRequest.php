<?php

namespace App\Modules\ServiceOrders\Requests;

class UpdateServiceOrderRequest extends StoreServiceOrderRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['recurrence_frequency'], $rules['recurrence_count']);

        return $rules;
    }

    protected function currentOrderId(): ?int
    {
        $id = $this->route('service_order') ?? $this->route('serviceOrder') ?? $this->route('id');

        return $id !== null ? (int) (is_object($id) ? $id->getKey() : $id) : null;
    }
}
