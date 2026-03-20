<?php

namespace Modules\PME\Invoicing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Shared\Traits\HasUlid;

class QuoteLine extends Model
{
    use HasUlid, SoftDeletes;
    // TODO: add fillable, casts, relationships
}
