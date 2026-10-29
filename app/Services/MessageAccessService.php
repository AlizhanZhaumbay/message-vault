<?php

namespace App\Services;

use App\Enums\DestroyMode;
use App\Enums\DestroyReason;
use App\Enums\MessageStatus;
use App\Exceptions\InvalidMessagePasswordException;
use App\Exceptions\MessageDestroyedException;
use App\Exceptions\MessageSealedException;
use App\Models\Message;
use Illuminate\Support\Facades\DB;

class MessageAccessService
{
    public function openMessage(string $id, ?string $password): Message
    {
        [$message, $error] = DB::transaction(function () use ($id, $password) {
            $message = Message::query()->with('attachments')->lockForUpdate()->findOrFail($id);

            if ($message->isPastDestroyDate()) {
                $this->destroyMessage($message, DestroyReason::DestroyDateReached);
            }

            $error = match (true) {
                $message->status() === MessageStatus::Destroyed => new MessageDestroyedException($message),
                $message->status() === MessageStatus::Sealed => new MessageSealedException($message),
                ! $message->checkPassword($password) => new InvalidMessagePasswordException,
                default => null,
            };

            if ($error === null) {
                $this->consumeView($message);
            }

            return [$message, $error];
        });

        if ($error !== null) {
            throw $error;
        }

        return $message;
    }

    public function destroyExpiredMessages(): int
    {
        return Message::query()
            ->whereNull('destroyed_at')
            ->where('destroy_mode', DestroyMode::AtDate)
            ->where('destroy_at', '<=', now())
            ->update([
                'body' => null,
                'destroyed_at' => now(),
                'destroyed_reason' => DestroyReason::DestroyDateReached,
            ]);
    }

    private function consumeView(Message $message): void
    {
        $body = $message->body;

        $message->views_count++;
        $message->first_viewed_at ??= now();

        $reason = $message->destroy_mode->reasonWhenViewsExhausted();

        if ($reason !== null && $message->remainingViews() === 0) {
            $this->destroyMessage($message, $reason);
            $message->body = $body;

            return;
        }

        $message->save();
    }

    private function destroyMessage(Message $message, DestroyReason $reason): void
    {
        $message->body = null;
        $message->destroyed_at = now();
        $message->destroyed_reason = $reason;
        $message->save();
    }
}
