<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Schedule::command('cpr:refresh-status')->dailyAt('00:05');
