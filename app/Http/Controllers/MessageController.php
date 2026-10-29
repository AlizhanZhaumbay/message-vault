<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListMessagesRequest;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Requests\UpdateMessageRequest;
use App\Http\Resources\MessageResource;
use App\Http\Resources\MessageSummaryResource;
use App\Models\Message;
use App\Services\MessageAccessService;
use App\Services\MessageService;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function __construct(
        private readonly MessageService $messages,
        private readonly MessageAccessService $access,
    ) {}

    public function index(ListMessagesRequest $request)
    {
        return MessageSummaryResource::collection($this->messages->listMessages($request->status()));
    }

    public function store(StoreMessageRequest $request)
    {
        [$message, $ownerToken] = $this->messages->createMessage(
            $request->validated(),
            $request->file('attachments', []),
        );

        return response()->json([
            ...(new MessageResource($message))->resolve($request),
            'owner_token' => $ownerToken,
        ], 201);
    }

    public function show(Request $request, string $message)
    {
        return new MessageResource(
            $this->access->openMessage($message, $request->header('X-Message-Password')),
        );
    }

    public function update(UpdateMessageRequest $request, Message $message)
    {
        return new MessageResource($this->messages->updateMessage($message, $request->validated()));
    }

    public function destroy(Message $message)
    {
        $this->messages->deleteMessage($message);

        return response()->noContent();
    }
}
