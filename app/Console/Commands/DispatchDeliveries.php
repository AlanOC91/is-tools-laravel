<?php

namespace App\Console\Commands;

use App\Jobs\DeliverRequest;
use App\Models\Delivery;
use Illuminate\Console\Command;

class DispatchDeliveries extends Command
{
    protected $signature = 'is-tools:dispatch-deliveries';

    protected $description = 'Queue pending request notifications from the durable outbox';

    public function handle(): int
    {
        $ids = Delivery::where('state', 'pending')->orderBy('id')->limit(100)->pluck('id');
        foreach ($ids as $id) {
            DeliverRequest::dispatch($id);
        }
        $this->info($ids->count().' pending notifications queued.');

        return self::SUCCESS;
    }
}
