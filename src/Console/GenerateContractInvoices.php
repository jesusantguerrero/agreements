<?php

namespace Insane\Agreements\Console;

use Illuminate\Console\Command;
use Insane\Agreements\Models\Contract;

class GenerateContractInvoices extends Command
{
    protected $signature = 'contracts:generate-invoices {--team= : Only this team} {--contract= : Only this contract}';

    protected $description = 'Create the invoices of every active contract whose next billing date has arrived';

    public function handle(): int
    {
        $created = 0;

        Contract::query()
            ->where('status', Contract::STATUS_ACTIVE)
            ->whereNotNull('next_invoice_date')
            ->whereDate('next_invoice_date', '<=', now())
            ->when($this->option('team'), fn ($q, $team) => $q->where('team_id', $team))
            ->when($this->option('contract'), fn ($q, $id) => $q->whereKey($id))
            ->orderBy('id')
            ->each(function (Contract $contract) use (&$created) {
                try {
                    $created += $contract->generateUpToDate()->count();
                } catch (\Throwable $e) {
                    report($e);
                    $this->error("Contract {$contract->id}: {$e->getMessage()}");
                }
            });

        $this->info("Invoices created: {$created}");

        return self::SUCCESS;
    }
}
