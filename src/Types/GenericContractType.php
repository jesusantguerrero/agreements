<?php

namespace Insane\Agreements\Types;

class GenericContractType extends BaseContractType
{
    public function key(): string
    {
        return 'generic';
    }

    public function label(): string
    {
        return 'Contrato';
    }
}
