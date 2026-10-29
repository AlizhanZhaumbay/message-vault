<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TombstoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'created_at' => $this->created_at,
            'first_viewed_at' => $this->first_viewed_at,
            'destroyed_at' => $this->destroyed_at,
            'destroyed_reason' => $this->destroyed_reason,
        ];
    }
}
