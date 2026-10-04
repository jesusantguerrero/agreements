<?php

namespace Insane\Agreements\Models;

use Illuminate\Database\Eloquent\Model;

class ContractRelation extends Model
{
    protected $fillable = ['contract_id', 'role', 'related_type', 'related_id'];

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function related()
    {
        return $this->morphTo();
    }
}
