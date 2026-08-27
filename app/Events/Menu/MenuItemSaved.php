<?php

namespace App\Events\Menu;

use App\Models\MenuItem;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MenuItemSaved
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public MenuItem $menuItem) {}
}
