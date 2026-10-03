<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_code' => $this->account_code,
            'debit' => (string) $this->debit,
            'credit' => (string) $this->credit,
        ];
    }
}
