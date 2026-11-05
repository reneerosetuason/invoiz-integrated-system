<?php

namespace App\Support;

use App\Models\ReturnRequest;
use App\Services\ReturnWorkflowService;

/** Compatibility entry point: physical receipt never claims that money was sent. */
class Returns
{
    public static function complete(ReturnRequest $return): void
    {
        abort_unless(auth()->check(), 403);
        ReturnWorkflowService::change($return, auth()->user(), 'receive');
    }
}
