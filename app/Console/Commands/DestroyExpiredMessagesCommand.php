<?php

namespace App\Console\Commands;

use App\Services\MessageAccessService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:messages:destroy-expired')]
#[Description('Destroy at_date messages whose destroy_at has passed')]
class DestroyExpiredMessagesCommand extends Command
{
    public function handle(MessageAccessService $access): int
    {
        $count = $access->destroyExpiredMessages();

        $this->components->info("Destroyed {$count} expired message(s).");

        return self::SUCCESS;
    }
}
