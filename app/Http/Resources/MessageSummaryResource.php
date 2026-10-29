<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'teaser' => $this->teaser,
            'status' => $this->status(),
            'has_password' => $this->requiresPassword(),
            'unlock_at' => $this->unlock_at,
            'seconds_until_unlock' => $this->secondsUntilUnlock(),
            'destroy_mode' => $this->destroy_mode,
            'remaining_views' => $this->remainingViews(),
            'attachments' => [
                'count' => $this->attachments->count(),
                'types' => $this->attachments->pluck('mime_type')->unique()->values(),
            ],
            'created_at' => $this->created_at,
        ];
    }
}
