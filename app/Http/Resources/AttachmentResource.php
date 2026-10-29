<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class AttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $expiresAt = now()->addMinutes(config('vault.download_link_ttl_minutes'));

        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'download_url' => URL::temporarySignedRoute('attachments.download', $expiresAt, ['attachment' => $this->id]),
            'download_url_expires_at' => $expiresAt,
        ];
    }
}
