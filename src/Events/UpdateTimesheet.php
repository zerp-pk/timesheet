<?php

namespace Zerp\Timesheet\Events;

use Zerp\Timesheet\Models\Timesheet;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;

class UpdateTimesheet
{
    use Dispatchable;

    public function __construct(
        public Request $request,
        public Timesheet $timesheet
    ) {}
}