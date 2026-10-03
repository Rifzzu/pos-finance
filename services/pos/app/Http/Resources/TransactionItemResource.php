<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'sku' => $this->sku,
            'product_name' => $this->product_name,
            'quantity' => $this->quantity,
            'unit_price' => (string) $this->unit_price,
            'subtotal' => (string) $this->subtotal,
        ];
    }
}
