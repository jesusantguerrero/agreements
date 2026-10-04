<?php

namespace Insane\Agreements\Concerns;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Insane\Agreements\Events\ContractInvoiceGenerated;
use Insane\Agreements\Support\Cycle;
use Insane\Journal\Models\Invoice\Invoice;

/**
 * Common billing behaviour of every contract: generate the invoice of each period once,
 * catch up missed periods, pause/resume/end, and report the period schedule.
 *
 * @mixin \Insane\Agreements\Models\Contract
 */
trait HasContractBilling
{
    /** Sets next_invoice_date from start_date/invoice_day (call on create). */
    public function initBilling(): static
    {
        $this->next_invoice_date = Cycle::first($this->cycle, $this->start_date, $this->invoice_day);
        if ($this->end_date && $this->next_invoice_date->gt($this->end_date)) {
            $this->next_invoice_date = null;
        }

        return $this;
    }

    public function isBillable(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->next_invoice_date !== null;
    }

    public function isDue(?Carbon $today = null): bool
    {
        return $this->isBillable() && $this->next_invoice_date->lte(($today ?? now())->copy()->startOfDay());
    }

    /** Bills next_invoice_date (even if it is in the future) and advances the schedule. */
    public function generateNextInvoice(): ?Invoice
    {
        if (! $this->isBillable()) {
            return null;
        }

        return $this->generateInvoiceFor($this->next_invoice_date->copy());
    }

    /** Bills every period due up to $today. Returns the invoices created. */
    public function generateUpToDate(?Carbon $today = null): Collection
    {
        $created = collect();
        $max = (int) config('agreements.max_catch_up', 24);

        while ($created->count() < $max && $this->isDue($today)) {
            $invoice = $this->generateNextInvoice();
            if (! $invoice) {
                break;
            }
            $created->push($invoice);
        }

        return $created;
    }

    /** Creates the invoice of the period starting on $period, once. */
    public function generateInvoiceFor(Carbon $period): ?Invoice
    {
        $key = $period->format('Y-m-d');
        $generated = $this->generated_invoice_dates ?? [];

        return DB::transaction(function () use ($period, $key, $generated) {
            $invoice = null;

            if (! in_array($key, $generated, true)) {
                $type = $this->contractType();
                $items = $type->invoiceLines($this, $period);
                $total = round(array_sum(array_column($items, 'amount')), 2);

                $invoice = Invoice::createDocument([
                    'team_id' => $this->team_id,
                    'user_id' => $this->user_id,
                    'client_id' => $this->client_id,
                    'invoiceable_type' => $this->getMorphClass(),
                    'invoiceable_id' => $this->getKey(),
                    'concept' => $type->invoiceConcept($this, $period),
                    'description' => $this->title,
                    'date' => $key,
                    'due_date' => $period->copy()->addDays($this->due_days ?? 0)->format('Y-m-d'),
                    'type' => Invoice::DOCUMENT_TYPE_INVOICE,
                    'resource_type_id' => Invoice::DOCUMENT_TYPE_INVOICE,
                    'subtotal' => $total,
                    'discount' => 0,
                    'total' => $total,
                    'taxes' => [],
                    'notes' => '',
                    'items' => $items,
                    ...($this->auto_issue ? ['status' => 'unpaid'] : []),
                ]);

                $generated[] = $key;
            }

            $this->generated_invoice_dates = array_values(array_unique($generated));
            $this->advanceFrom($period);
            $this->save();

            if ($invoice) {
                ContractInvoiceGenerated::dispatch($this, $invoice, $period);
            }

            return $invoice;
        });
    }

    /** Moves next_invoice_date past $period; ends the contract when there is nothing left to bill. */
    protected function advanceFrom(Carbon $period): void
    {
        $next = Cycle::next($this->cycle, $period, $this->invoice_day);

        if ($next && $this->end_date && $next->gt($this->end_date)) {
            $next = null;
        }

        $this->next_invoice_date = $next;
        if (! $next && $this->status === self::STATUS_ACTIVE) {
            $this->status = self::STATUS_ENDED;
        }
    }

    public function pause(): static
    {
        if ($this->status === self::STATUS_ACTIVE) {
            $this->update(['status' => self::STATUS_PAUSED]);
        }

        return $this;
    }

    /** Resumes billing from today on: periods skipped while paused are not billed. */
    public function resume(?Carbon $today = null): static
    {
        if ($this->status !== self::STATUS_PAUSED) {
            return $this;
        }
        $today = ($today ?? now())->copy()->startOfDay();
        $next = $this->next_invoice_date;
        while ($next && $next->lt($today)) {
            $next = Cycle::next($this->cycle, $next, $this->invoice_day);
        }
        if ($next && $this->end_date && $next->gt($this->end_date)) {
            $next = null;
        }
        $this->update(['status' => $next ? self::STATUS_ACTIVE : self::STATUS_ENDED, 'next_invoice_date' => $next]);

        return $this;
    }

    /** Ends the contract on $date: nothing after it is billed. */
    public function end(?Carbon $date = null): static
    {
        $date = ($date ?? now())->copy()->startOfDay();
        $next = $this->next_invoice_date && $this->next_invoice_date->lte($date) ? $this->next_invoice_date : null;
        $this->update([
            'end_date' => $date->format('Y-m-d'),
            'next_invoice_date' => $next,
            'status' => $next ? $this->status : self::STATUS_ENDED,
        ]);

        return $this;
    }

    public function extend(Carbon $until): static
    {
        $this->end_date = $until;
        if (! $this->next_invoice_date && in_array($this->status, [self::STATUS_ENDED, self::STATUS_ACTIVE], true)) {
            $last = collect($this->generated_invoice_dates ?? [])->sort()->last();
            $this->next_invoice_date = $last
                ? Cycle::next($this->cycle, Carbon::parse($last), $this->invoice_day)
                : Cycle::first($this->cycle, $this->start_date, $this->invoice_day);
            if ($this->next_invoice_date && $this->next_invoice_date->gt($until)) {
                $this->next_invoice_date = null;
            }
            $this->status = $this->next_invoice_date ? self::STATUS_ACTIVE : self::STATUS_ENDED;
        }
        $this->save();

        return $this;
    }

    /** Unpaid balance of the issued invoices. */
    public function outstanding(): float
    {
        return round((float) $this->invoices()->where('status', '!=', 'draft')->sum('debt'), 2);
    }

    public function isOverdue(?Carbon $today = null): bool
    {
        return $this->invoices()
            ->whereNotIn('status', ['paid', 'draft', 'canceled'])
            ->where('debt', '>', 0)
            ->whereDate('due_date', '<', ($today ?? now())->format('Y-m-d'))
            ->exists();
    }

    /**
     * Periods with their invoice and state, oldest first:
     * billed ones (paid, partial, overdue, pending, draft) and the next $upcoming ones.
     */
    public function schedule(int $upcoming = 3, ?Carbon $today = null): array
    {
        $today = ($today ?? now())->copy()->startOfDay();
        $invoices = $this->invoices()->get(['id', 'date', 'due_date', 'number', 'series', 'total', 'debt', 'status'])
            ->keyBy(fn ($invoice) => Carbon::parse($invoice->date)->format('Y-m-d'));

        $rows = collect($this->generated_invoice_dates ?? [])->sort()->values()->map(function ($date) use ($invoices, $today) {
            $invoice = $invoices[$date] ?? null;
            $state = 'missing';
            if ($invoice) {
                $state = match (true) {
                    $invoice->status === 'draft' => 'draft',
                    (float) $invoice->debt <= 0 => 'paid',
                    Carbon::parse($invoice->due_date)->lt($today) => 'overdue',
                    (float) $invoice->debt < (float) $invoice->total => 'partial',
                    default => 'pending',
                };
            }

            return [
                'date' => $date,
                'label' => Cycle::periodLabel($this->cycle, Carbon::parse($date)),
                'state' => $state,
                'invoice' => $invoice ? [
                    'id' => $invoice->id,
                    'number' => $invoice->number,
                    'total' => (float) $invoice->total,
                    'debt' => (float) $invoice->debt,
                    'due_date' => Carbon::parse($invoice->due_date)->format('Y-m-d'),
                ] : null,
            ];
        });

        $next = $this->isBillable() ? $this->next_invoice_date->copy() : null;
        for ($i = 0; $next && $i < $upcoming; $i++) {
            $rows->push([
                'date' => $next->format('Y-m-d'),
                'label' => Cycle::periodLabel($this->cycle, $next),
                'state' => $next->lte($today) ? 'due' : 'upcoming',
                'invoice' => null,
            ]);
            $next = Cycle::next($this->cycle, $next, $this->invoice_day);
            if ($next && $this->end_date && $next->gt($this->end_date)) {
                $next = null;
            }
        }

        return $rows->values()->all();
    }
}
