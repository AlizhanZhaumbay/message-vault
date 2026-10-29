<?php

namespace App\Http\Controllers;

use App\Http\Resources\StatsResource;
use App\Services\MessageService;

class ShowStatsController extends Controller
{
    public function __invoke(MessageService $messages)
    {
        return new StatsResource($messages->getStats());
    }
}
