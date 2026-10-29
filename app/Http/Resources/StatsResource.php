<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StatsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $next = $this->resource['next_unlock'];

        return [
            'messages' => [
                'sealed' => $this->resource['sealed'],
                'available' => $this->resource['available'],
                'destroyed' => $this->resource['destroyed'],
            ],
            'next_unlock' => $next === null ? null : [
                'id' => $next->id,
                'title' => $next->title,
                'unlock_at' => $next->unlock_at,
                'seconds_remaining' => $next->secondsUntilUnlock(),
            ],
            'files' => [
                'count' => $this->resource['total_files'],
                'total_size_bytes' => $this->resource['total_files_size'],
            ],
        ];
    }
}
