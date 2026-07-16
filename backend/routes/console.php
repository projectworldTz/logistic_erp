<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sla:check-shipments')->hourly();
Schedule::command('reports:send-scheduled')->hourly();
Schedule::command('subscriptions:generate-invoices')->daily();
