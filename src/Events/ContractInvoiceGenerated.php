<?php

namespace Insane\Agreements\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Carbon;
use Insane\Agreements\Models\Contract;
use Insane\Journal\Models\Invoice\Invoice;

/** Fired after a contract creates an invoice. Apps use it for what journal doesn't store (currency…). */
class ContractInvoiceGenerated
{
    use Dispatchable;

    public function __construct(public Contract $contract, public Invoice $invoice, public Carbon $period)
    {
    }
}
