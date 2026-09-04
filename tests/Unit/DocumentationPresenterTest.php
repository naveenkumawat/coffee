<?php

namespace Tests\Unit;

use App\Support\Documentation\DocumentationCatalog;
use App\Support\Documentation\DocumentationPresenter;
use Tests\TestCase;

class DocumentationPresenterTest extends TestCase
{
    public function test_groups_administrator_modules_in_canonical_group_order(): void
    {
        $catalog = new DocumentationCatalog;
        $presenter = new DocumentationPresenter($catalog);

        $groups = $presenter->groupModulesForRole('administrator');

        $this->assertSame(
            ['System', 'Catalog', 'Orders', 'Marketing', 'Loyalty', 'Operations', 'Finance', 'Launch'],
            array_keys($groups),
        );
        $this->assertSame('dashboard', $groups['System'][0]['slug']);
    }

    public function test_group_keys_only_include_groups_the_role_can_see(): void
    {
        $catalog = new DocumentationCatalog;
        $presenter = new DocumentationPresenter($catalog);

        $this->assertSame(['Bar'], $presenter->groupKeys($catalog->modulesForRole('barista')));
        $this->assertSame(['Kitchen'], $presenter->groupKeys($catalog->modulesForRole('chef')));
    }

    public function test_grouping_preserves_every_module(): void
    {
        $catalog = new DocumentationCatalog;
        $presenter = new DocumentationPresenter($catalog);

        $modules = $catalog->modulesForRole('operator');
        $grouped = $presenter->groupModules($modules);

        $this->assertCount(count($modules), array_merge(...array_values($grouped)));
    }

    public function test_navigation_returns_slug_and_title_pairs_per_group(): void
    {
        $catalog = new DocumentationCatalog;
        $presenter = new DocumentationPresenter($catalog);

        $navigation = $presenter->navigation($catalog->modulesForRole('chef'));

        $this->assertSame(['slug', 'title'], array_keys($navigation['Kitchen'][0]));
        $this->assertSame(
            ['slug' => 'kitchen-queue', 'title' => 'Kitchen Queue'],
            $navigation['Kitchen'][0],
        );
    }

    public function test_grouping_no_modules_returns_no_groups(): void
    {
        $presenter = new DocumentationPresenter(new DocumentationCatalog);

        $this->assertSame([], $presenter->groupModules([]));
        $this->assertSame([], $presenter->groupModulesForRole('customer'));
    }
}
