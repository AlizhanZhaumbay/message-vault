<?php

namespace App\Services;

use App\Enums\DestroyMode;
use App\Enums\MessageStatus;
use App\Exceptions\MessageAlreadyViewedException;
use App\Models\Attachment;
use App\Models\Message;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class MessageService
{
    public function createMessage(array $data, array $files = []): array
    {
        $ownerToken = Str::random(40);

        $message = new Message([
            ...Arr::except($data, ['password', 'attachments']),
            'password_hash' => isset($data['password']) ? Hash::make($data['password']) : null,
            'owner_token_hash' => hash('sha256', $ownerToken),
        ]);
        $this->dropInapplicableRules($message);
        $message->save();

        try {
            foreach ($files as $file) {
                $this->storeAttachment($message, $file);
            }
        } catch (Throwable $e) {
            $this->deleteMessage($message);

            throw $e;
        }

        return [$message->load('attachments'), $ownerToken];
    }

    public function updateMessage(Message $message, array $data): Message
    {
        return DB::transaction(function () use ($message, $data) {
            $message = Message::query()->lockForUpdate()->findOrFail($message->id);

            if ($message->wasViewed()) {
                throw new MessageAlreadyViewedException;
            }

            $message->fill(Arr::except($data, ['password']));

            if (array_key_exists('password', $data)) {
                $message->password_hash = $data['password'] !== null ? Hash::make($data['password']) : null;
            }

            $this->dropInapplicableRules($message);
            $message->save();

            return $message->load('attachments');
        });
    }

    public function deleteMessage(Message $message): void
    {
        $message->delete();

        $this->disk()->deleteDirectory("messages/{$message->id}");
    }

    public function listMessages(?MessageStatus $status): LengthAwarePaginator
    {
        return Message::query()
            ->with('attachments:id,message_id,mime_type')
            ->when($status, fn ($query) => $query->whereStatus($status))
            ->latest()
            ->paginate(config('vault.per_page'))
            ->withQueryString();
    }

    public function getStats(): array
    {
        return [
            'sealed' => Message::query()->whereStatus(MessageStatus::Sealed)->count(),
            'available' => Message::query()->whereStatus(MessageStatus::Available)->count(),
            'destroyed' => Message::query()->whereStatus(MessageStatus::Destroyed)->count(),
            'next_unlock' => Message::query()->whereStatus(MessageStatus::Sealed)->oldest('unlock_at')->first(),
            'total_files' => Attachment::query()->count(),
            'total_files_size' => (int) Attachment::query()->sum('size'),
        ];
    }

    public function purgeDestroyedAttachments(): int
    {
        $cutoff = now()->subMinutes(config('vault.download_link_ttl_minutes') + 1);

        $attachments = Attachment::query()
            ->whereHas('message', fn ($query) => $query->where('destroyed_at', '<=', $cutoff))
            ->get();

        $this->disk()->delete($attachments->pluck('storage_path')->all());
        Attachment::query()->whereKey($attachments->modelKeys())->delete();

        return $attachments->count();
    }

    private function storeAttachment(Message $message, UploadedFile $file): Attachment
    {
        $path = $file->store("messages/{$message->id}", ['disk' => config('vault.disk')]);

        return $message->attachments()->create([
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'storage_path' => $path,
        ]);
    }

    private function dropInapplicableRules(Message $message): void
    {
        if ($message->destroy_mode !== DestroyMode::AfterViews) {
            $message->max_views = null;
        }

        if ($message->destroy_mode !== DestroyMode::AtDate) {
            $message->destroy_at = null;
        }
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('vault.disk'));
    }
}
