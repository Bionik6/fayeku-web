<?php

namespace Modules\PME\Clients\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Shared\Traits\HasUlid;

class Client extends Model
{
    use HasUlid, SoftDeletes;

    protected $fillable = [
        'company_id', 'name', 'phone', 'email', 'address', 'tax_id',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(\Modules\Auth\Models\Company::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(\Modules\PME\Invoicing\Models\Invoice::class);
    }
}
