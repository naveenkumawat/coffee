<?php

namespace App\View\Components\Admin;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class StatCard extends Component
{
    public function __construct(
        public string $label,
        public string|int $value,
        public string $tone = 'amber',
    ) {}

    public function render(): View|Closure|string
    {
        return view('components.admin.stat-card');
    }
}
