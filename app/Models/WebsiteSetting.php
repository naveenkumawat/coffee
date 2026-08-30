<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'section', 'value_type', 'value'])]
class WebsiteSetting extends Model
{
    //
}
