<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 出金自动重试暂时关闭，先由财务通过 withdrawals/finance/retry 人工重试，稳定后再启用
// Schedule::command('withdrawals:auto-wallet-retry')
//     ->everyMinute()
//     ->onOneServer()
//     ->withoutOverlapping();

Schedule::command('position-records:sync-statuses')
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('community-activities:update-statuses')
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping();
