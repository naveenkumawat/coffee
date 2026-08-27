<?php

namespace App\Events\Menu;

use App\Models\MenuCategory;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MenuCategorySaved
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public MenuCategory $menuCategory) {}
}
