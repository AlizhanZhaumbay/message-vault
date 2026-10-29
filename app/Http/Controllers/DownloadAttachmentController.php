<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;

class DownloadAttachmentController extends Controller
{
    public function __invoke(Attachment $attachment)
    {
        return Storage::disk(config('vault.disk'))->download(
            $attachment->storage_path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type],
        );
    }
}
