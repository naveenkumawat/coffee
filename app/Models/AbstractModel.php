<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

abstract class AbstractModel extends Model
{
    public function getId(): mixed
    {
        return $this->getKey();
    }
}
