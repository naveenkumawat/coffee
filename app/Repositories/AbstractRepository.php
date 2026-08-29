<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Model;

abstract class AbstractRepository
{
    protected function persist(Model $model, array $attributes): Model
    {
        $model->fill($attributes);
        $model->save();

        return $model->fresh();
    }

    protected function remove(Model $model): void
    {
        $model->delete();
    }
}
