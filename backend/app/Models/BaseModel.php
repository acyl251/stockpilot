<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;

abstract class BaseModel extends Model
{
    protected $guarded = ['id'];

    public $timestamps = true;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    /**
     * Automatically inject organisation_id on create.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Model $model) {
            if (
                in_array('organisation_id', $model->getFillable())
                && empty($model->organisation_id)
                && app()->bound('current_organisation_id')
            ) {
                $model->organisation_id = app('current_organisation_id');
            }
        });
    }
}
