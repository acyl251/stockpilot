<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $organisationId = app()->bound('current_organisation_id')
            ? app('current_organisation_id')
            : null;

        if ($organisationId) {
            $builder->where($model->getTable() . '.organisation_id', $organisationId);
        } else {
            // Aucun contexte tenant : aucun résultat (empêche les fuites inter-tenant)
            $builder->whereRaw('1 = 0');
        }
    }
}
