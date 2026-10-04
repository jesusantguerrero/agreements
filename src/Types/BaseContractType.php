<?php

namespace Insane\Agreements\Types;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Insane\Agreements\Contracts\ContractType;
use Insane\Agreements\Models\Contract;
use Insane\Agreements\Support\Cycle;

/** Fixed amount per period: one line "<title> — <period>". Types override what they need. */
abstract class BaseContractType implements ContractType
{
    public function metaRules(): array
    {
        return [];
    }

    public function relationRoles(): array
    {
        return [];
    }

    public function invoiceConcept(Contract $contract, Carbon $period): string
    {
        return Str::limit($contract->title, 120, '');
    }

    public function invoiceLines(Contract $contract, Carbon $period): array
    {
        $concept = $contract->cycle === Cycle::ONE_TIME
            ? $contract->title
            : $contract->title . ' — ' . Cycle::periodLabel($contract->cycle, $period);

        return [[
            'index' => 0,
            'concept' => Str::limit($concept, 250, ''),
            'quantity' => 1,
            'price' => (float) $contract->amount,
            'amount' => (float) $contract->amount,
            'taxes' => [],
        ]];
    }
}
