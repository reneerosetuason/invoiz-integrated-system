<?php

namespace App\Console\Commands;

use App\Models\AccountDeletionRequest;
use App\Services\BuyerAccountDeletionService;
use Illuminate\Console\Command;

class ProcessBuyerAccountDeletions extends Command
{
    protected $signature = 'buyers:process-account-deletions';

    protected $description = 'Remind buyers and archive eligible accounts after the 30-day grace period';

    public function handle(BuyerAccountDeletionService $service): int
    {
        $failures = 0;
        AccountDeletionRequest::whereIn('status', ['pending', 'on_hold'])
            ->where('scheduled_archive_at', '<=', now()->addDays(7))->chunkById(100, function ($requests) use ($service, &$failures) {
                foreach ($requests as $request) {
                    try {
                        $service->process($request->id);
                    } catch (\Throwable $error) {
                        report($error);
                        $failures++;
                        $this->error('Request #'.$request->id.' was not processed. See the application log.');
                    }
                }
            });
        $this->info('Buyer deletion processing finished.');

        return $failures ? self::FAILURE : self::SUCCESS;
    }
}
