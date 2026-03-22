<?php

namespace Modules\Compta\Export\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\Models\Company;
use Modules\Compta\Export\Enums\ExportFormat;
use Modules\Shared\Models\User;
use Modules\Shared\Traits\HasUlid;

class ExportHistory extends Model
{
    use HasUlid;

    protected $fillable = [
        'firm_id',
        'user_id',
        'period',
        'format',
        'scope',
        'client_ids',
        'clients_count',
        'file_path',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'format' => ExportFormat::class,
        'client_ids' => 'array',
        'clients_count' => 'integer',
    ];

    /** @return BelongsTo<Company, $this> */
    public function firm(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'firm_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
