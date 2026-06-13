<?php

use App\Models\ExpenseLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-delete expense logs older than 3 days — money sent transactions are never deleted
Schedule::call(function () {
    ExpenseLog::where('created_at', '<', now()->subDays(3))->delete();
})->daily()->name('delete-old-expense-logs');
