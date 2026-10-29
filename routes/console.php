<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:messages:destroy-expired')->everyMinute();
Schedule::command('app:attachments:purge')->everyMinute();
