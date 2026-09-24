<?php

namespace App\Modules\Sales\Models;

use App\Modules\PetShopServices\Models\PetShopService;
use App\Modules\Products\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleQuoteItem extends Model
{
    protected $table = 'sale_quote_items';

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(SaleQuote::class, 'sale_quote_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function petShopService(): BelongsTo
    {
        return $this->belongsTo(PetShopService::class, 'petshop_service_id');
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'product' => 'Produto',
            'service' => 'Serviço',
            default => 'Avulso',
        };
    }
}
