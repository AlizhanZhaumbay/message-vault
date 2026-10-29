<?php

namespace App\Console\Commands;

use App\Services\MessageService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:attachments:purge')]
#[Description('Delete files of destroyed messages once their download links have expired')]
class PurgeDestroyedAttachmentsCommand extends Command
{
    public function handle(MessageService $messages): int
    {
        $count = $messages->purgeDestroyedAttachments();

        $this->components->info("Purged {$count} attachment(s).");

        return self::SUCCESS;
    }
}
