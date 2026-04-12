<?php

namespace App\Models\Compta;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Auth\Company;
use App\Enums\Compta\ExportFormat;
use App\Models\Shared\User;
use App\Traits\Shared\HasUlid;

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
