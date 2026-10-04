<?php

return [
    /*
     * Contract types, key => class implementing Insane\Agreements\Contracts\ContractType.
     * Each app registers its own (freelance in Neatlancer, rent in NeatRents, admission in Academic).
     */
    'types' => [
        'generic' => \Insane\Agreements\Types\GenericContractType::class,
    ],

    // Who pays. Neatlancer, NeatRents and Academic all use the CRM client.
    'client_model' => 'App\\Domains\\CRM\\Models\\Client',

    // Max invoices a single generateUpToDate() run creates for one contract (catch-up safety net).
    'max_catch_up' => 24,
];
