<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PipelineStage extends Model
{
    protected $fillable = ['pipeline_id', 'name', 'sort_order', 'color', 'is_final', 'is_fail'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_final' => 'boolean',
        'is_fail' => 'boolean',
    ];

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function automations(): HasMany
    {
        return $this->hasMany(Automation::class);
    }
}
