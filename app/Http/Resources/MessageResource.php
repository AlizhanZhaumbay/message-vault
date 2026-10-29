<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'teaser' => $this->teaser,
            'body' => $this->body,
            'status' => $this->status(),
            'has_password' => $this->requiresPassword(),
            'unlock_at' => $this->unlock_at,
            'destroy_mode' => $this->destroy_mode,
            'max_views' => $this->max_views,
            'destroy_at' => $this->destroy_at,
            'views_count' => $this->views_count,
            'remaining_views' => $this->remainingViews(),
            'first_viewed_at' => $this->first_viewed_at,
            'destroyed_at' => $this->destroyed_at,
            'destroyed_reason' => $this->destroyed_reason,
            'attachments' => AttachmentResource::collection($this->attachments),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
