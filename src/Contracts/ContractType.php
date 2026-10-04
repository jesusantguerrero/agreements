<?php

namespace Insane\Agreements\Contracts;

use Illuminate\Support\Carbon;
use Insane\Agreements\Models\Contract;

/**
 * What changes between kinds of contract (freelance retainer, rent, admission).
 * The billing behaviour itself is common and lives in HasContractBilling.
 */
interface ContractType
{
    /** Key stored in contracts.type, e.g. 'freelance'. */
    public function key(): string;

    public function label(): string;

    /** Laravel validation rules for meta_data (keys without the "meta_data." prefix). */
    public function metaRules(): array;

    /**
     * Allowed relation roles: role => ['model' => class, 'required' => bool, 'multiple' => bool].
     * e.g. ['project' => ['model' => Project::class, 'required' => false, 'multiple' => false]]
     */
    public function relationRoles(): array;

    /**
     * Invoice lines for the period that starts on $period, in journal's items[] shape:
     * [['concept' => ..., 'quantity' => 1, 'price' => ..., 'amount' => ..., 'taxes' => []], ...]
     */
    public function invoiceLines(Contract $contract, Carbon $period): array;

    /** Concept (title) of the generated invoice. */
    public function invoiceConcept(Contract $contract, Carbon $period): string;
}
