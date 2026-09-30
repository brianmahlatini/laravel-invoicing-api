<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('invoices:remind-overdue')->dailyAt('08:00')->withoutOverlapping()->onOneServer();
