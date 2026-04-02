<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\{Builder, Model, Scope};

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $customerId = app('tenant.customer_id');

        // Platform users (customer_id = null) → no scope → see all data
        if ($customerId) {
            $builder->where($model->getTable() . '.customer_id', $customerId);
        }
    }
}
