<?php

namespace Insane\Agreements\Models;

use Illuminate\Database\Eloquent\Model;
use Insane\Agreements\Agreements;
use Insane\Agreements\Concerns\HasContractBilling;
use Insane\Agreements\Contracts\ContractType;
use Insane\Journal\Models\Invoice\Invoice;
use InvalidArgumentException;

class Contract extends Model
{
    use HasContractBilling;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_ENDED = 'ended';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_ACTIVE, self::STATUS_PAUSED, self::STATUS_ENDED, self::STATUS_CANCELLED];

    protected $fillable = [
        'team_id', 'user_id', 'type', 'client_id', 'title', 'number', 'description', 'amount',
        'currency_code', 'cycle', 'start_date', 'end_date', 'invoice_day', 'due_days',
        'next_invoice_date', 'generated_invoice_dates', 'auto_issue', 'late_fee', 'late_fee_type',
        'grace_days', 'status', 'meta_data',
    ];

    protected $casts = [
        'amount' => 'float',
        'late_fee' => 'float',
        'invoice_day' => 'integer',
        'due_days' => 'integer',
        'grace_days' => 'integer',
        'auto_issue' => 'boolean',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'next_invoice_date' => 'date:Y-m-d',
        'generated_invoice_dates' => 'array',
        'meta_data' => 'array',
    ];

    public function client()
    {
        return $this->belongsTo(Agreements::clientModel());
    }

    public function invoices()
    {
        return $this->morphMany(Invoice::class, 'invoiceable');
    }

    public function contractRelations()
    {
        return $this->hasMany(ContractRelation::class);
    }

    public function contractType(): ContractType
    {
        return Agreements::type($this->type);
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    /** First related model for a role (e.g. 'project'), or null. */
    public function related(string $role): ?Model
    {
        return $this->contractRelations->firstWhere('role', $role)?->related;
    }

    /**
     * Replaces the relations with role => model|id (or list of them), checked against the type's roles.
     * Ids are resolved with the role's model class.
     */
    public function syncRelations(array $relations): void
    {
        $roles = $this->contractType()->relationRoles();

        foreach ($roles as $role => $definition) {
            if (($definition['required'] ?? false) && empty($relations[$role])) {
                throw new InvalidArgumentException("The contract needs a [$role].");
            }
        }

        $rows = [];
        foreach ($relations as $role => $values) {
            if ($values === null || $values === [] || $values === '') {
                continue;
            }
            if (! isset($roles[$role])) {
                throw new InvalidArgumentException("Role [$role] is not allowed for [{$this->type}] contracts.");
            }
            $values = is_array($values) ? $values : [$values];
            if (count($values) > 1 && ! ($roles[$role]['multiple'] ?? false)) {
                throw new InvalidArgumentException("Only one [$role] per contract.");
            }
            foreach ($values as $value) {
                $model = $value instanceof Model ? $value : $roles[$role]['model']::findOrFail($value);
                $rows[] = ['role' => $role, 'related_type' => $model->getMorphClass(), 'related_id' => $model->getKey()];
            }
        }

        $this->contractRelations()->delete();
        foreach ($rows as $row) {
            $this->contractRelations()->create($row);
        }
        $this->unsetRelation('contractRelations');
    }
}
