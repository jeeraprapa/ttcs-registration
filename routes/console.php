<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('confirmations:dispatch')->everyMinute()->withoutOverlapping();
