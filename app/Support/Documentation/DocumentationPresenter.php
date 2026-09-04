<?php

namespace App\Support\Documentation;

/**
 * Thin presentation helpers over DocumentationCatalog output.
 *
 * @phpstan-import-type DocumentationModule from DocumentationCatalog
 */
class DocumentationPresenter
{
    public function __construct(
        protected DocumentationCatalog $catalog,
    ) {}

    /**
     * Groups modules by their group key, in canonical group order.
     *
     * @param  list<DocumentationModule>  $modules
     * @return array<string, list<DocumentationModule>>
     */
    public function groupModules(array $modules): array
    {
        $grouped = [];

        foreach ($modules as $module) {
            $grouped[$module['group']][] = $module;
        }

        return $this->sortByGroupOrder($grouped);
    }

    /**
     * Groups every module the role can see, in canonical group order.
     *
     * @return array<string, list<DocumentationModule>>
     */
    public function groupModulesForRole(string $role): array
    {
        return $this->groupModules($this->catalog->modulesForRole($role));
    }

    /**
     * Group keys present in the given modules, in canonical group order.
     *
     * @param  list<DocumentationModule>  $modules
     * @return list<string>
     */
    public function groupKeys(array $modules): array
    {
        return array_keys($this->groupModules($modules));
    }

    /**
     * Slug/title pairs per group, for building a navigation menu.
     *
     * @param  list<DocumentationModule>  $modules
     * @return array<string, list<array{slug: string, title: string}>>
     */
    public function navigation(array $modules): array
    {
        $navigation = [];

        foreach ($this->groupModules($modules) as $group => $groupModules) {
            $navigation[$group] = array_map(
                fn (array $module): array => [
                    'slug' => $module['slug'],
                    'title' => $module['title'],
                ],
                $groupModules,
            );
        }

        return $navigation;
    }

    /**
     * @param  array<string, list<DocumentationModule>>  $grouped
     * @return array<string, list<DocumentationModule>>
     */
    protected function sortByGroupOrder(array $grouped): array
    {
        $ordered = [];

        foreach ($this->catalog->groupOrder() as $group) {
            if (isset($grouped[$group])) {
                $ordered[$group] = $grouped[$group];
                unset($grouped[$group]);
            }
        }

        return [...$ordered, ...$grouped];
    }
}
