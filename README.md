# insane/agreements

Contracts that bill on a cycle, on top of [`insane/journal`](https://github.com/jesusantguerrero/journal).
One contract = who pays, how much, how often and from when; it generates journal invoices
(`invoices.invoiceable` = the contract) and keeps track of which periods are billed.

Used by Neatlancer Studio (freelance retainers). Planned for NeatRents (`Rent`) and Academic (`Admission`).

## Install

```bash
composer require insane/agreements:dev-main
php artisan migrate
```

Register the app's contract types in `config/agreements.php`:

```php
'types' => [
    'freelance' => App\Domains\Contracts\FreelanceContractType::class,
],
```

A type implements `Insane\Agreements\Contracts\ContractType` (or extends `Types\BaseContractType`):
validation of `meta_data`, allowed relation roles, and the invoice lines for a period.
Returning no lines (e.g. an hourly contract with no hours in the period) advances the cycle without an invoice.

## Billing

```php
$contract->generateNextInvoice();   // bills next_invoice_date and advances it
$contract->generateUpToDate();      // catches up every due period
$contract->schedule(12);            // periods with their invoice and status (paid, pending, overdue, upcoming)
$contract->pause(); $contract->resume(); $contract->end($date);
```

`php artisan contracts:generate-invoices` bills every active contract that is due; schedule it daily.
After each invoice the package fires `Insane\Agreements\Events\ContractInvoiceGenerated`
so the app can set what journal doesn't store (currency, business line…).

## Cycles

`monthly`, `biweekly`, `weekly`, `quarterly`, `yearly`, `one_time`. Monthly-like cycles keep `invoice_day`
(31 → last day of short months).
