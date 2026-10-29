<?php

namespace App\Http\Controllers;

use App\Http\Resources\MessageResource;
use App\Models\Message;

class PreviewMessageController extends Controller
{
    public function __invoke(Message $message)
    {
        return new MessageResource($message->load('attachments'));
    }
}
