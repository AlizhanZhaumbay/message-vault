<?php

namespace Database\Seeders;

use App\Enums\DestroyMode;
use App\Enums\DestroyReason;
use App\Models\Message;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (Message::query()->exists()) {
            $this->command->info('Messages already exist, skipping demo seed.');

            return;
        }

        $this->seedMessage('demo-owner-welcome', [
            'title' => 'Welcome to the Vault',
            'teaser' => 'Open me, I will stay',
            'body' => 'This message never self-destructs. Open it as many times as you like.',
            'destroy_mode' => DestroyMode::Never,
        ]);

        $this->seedMessage('demo-owner-capsule', [
            'title' => 'Time capsule',
            'teaser' => 'Come back in a week',
            'body' => 'Hello from the past!',
            'unlock_at' => now()->addWeek(),
            'destroy_mode' => DestroyMode::OnRead,
        ]);

        $this->seedMessage('demo-owner-recipe', [
            'title' => 'Secret recipe',
            'teaser' => 'Password hint: the classic one',
            'body' => 'Two eggs, a cup of flour and a pinch of Docker.',
            'password_hash' => Hash::make('letmein'),
            'destroy_mode' => DestroyMode::AfterViews,
            'max_views' => 3,
        ]);

        $this->seedMessage('demo-owner-burn', [
            'title' => 'Burn after reading',
            'body' => 'You are the only one who will ever see this.',
            'destroy_mode' => DestroyMode::OnRead,
        ]);

        $this->seedMessage('demo-owner-ttl', [
            'title' => 'Short-lived note',
            'teaser' => 'Gone in 10 minutes',
            'body' => 'The scheduler will destroy me soon.',
            'destroy_mode' => DestroyMode::AtDate,
            'destroy_at' => now()->addMinutes(10),
        ]);

        $this->seedMessage('demo-owner-ashes', [
            'title' => 'Already burned',
            'destroy_mode' => DestroyMode::OnRead,
            'views_count' => 1,
            'first_viewed_at' => now()->subDay(),
            'destroyed_at' => now()->subDay(),
            'destroyed_reason' => DestroyReason::ReadOnce,
        ]);

        $this->command->info('Seeded 6 demo messages (owner tokens are listed in README).');
    }

    private function seedMessage(string $ownerToken, array $attributes): void
    {
        Message::create([...$attributes, 'owner_token_hash' => hash('sha256', $ownerToken)]);
    }
}
