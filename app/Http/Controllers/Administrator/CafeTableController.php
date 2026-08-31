<?php

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\Controller;
use App\Http\Requests\CafeTable\CafeTableStoreRequest;
use App\Http\Requests\CafeTable\CafeTableUpdateRequest;
use App\Models\CafeTable;
use App\Repositories\CafeTable\CafeTableRepositoryInterface;
use App\Services\CafeTable\CafeTableServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class CafeTableController extends Controller
{
    public function __construct(
        protected CafeTableRepositoryInterface $tables,
        protected CafeTableServiceInterface $service,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', CafeTable::class);

        return view('administrator.cafe-tables.index', [
            'tables' => $this->tables->paginateForAdmin(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', CafeTable::class);

        return view('administrator.cafe-tables.create', [
            'table' => new CafeTable([
                'is_active' => true,
                'sort_order' => 100,
            ]),
        ]);
    }

    public function store(CafeTableStoreRequest $request): RedirectResponse
    {
        $table = $this->service->store($request->validated());

        return redirect()
            ->route('administrator.cafe-tables.edit', $table)
            ->with('status', 'Café table created successfully.');
    }

    public function edit(CafeTable $cafeTable): View
    {
        $this->authorize('update', $cafeTable);

        return view('administrator.cafe-tables.edit', [
            'table' => $cafeTable,
        ]);
    }

    public function update(CafeTableUpdateRequest $request, CafeTable $cafeTable): RedirectResponse
    {
        $this->authorize('update', $cafeTable);

        $this->service->update($cafeTable, $request->validated());

        return redirect()
            ->route('administrator.cafe-tables.edit', $cafeTable)
            ->with('status', 'Café table updated successfully.');
    }

    public function destroy(CafeTable $cafeTable): RedirectResponse
    {
        $this->authorize('delete', $cafeTable);

        $this->service->delete($cafeTable);

        return redirect()
            ->route('administrator.cafe-tables.index')
            ->with('status', 'Café table archived successfully.');
    }

    public function toggle(CafeTable $cafeTable): RedirectResponse
    {
        $this->authorize('update', $cafeTable);

        $this->service->setActive($cafeTable, ! $cafeTable->is_active);

        return redirect()
            ->route('administrator.cafe-tables.index')
            ->with('status', $cafeTable->fresh()?->is_active ? 'Café table activated.' : 'Café table deactivated.');
    }

    public function moveUp(CafeTable $cafeTable): RedirectResponse
    {
        $this->authorize('update', $cafeTable);
        $this->service->move($cafeTable, 'up');

        return redirect()->route('administrator.cafe-tables.index');
    }

    public function moveDown(CafeTable $cafeTable): RedirectResponse
    {
        $this->authorize('update', $cafeTable);
        $this->service->move($cafeTable, 'down');

        return redirect()->route('administrator.cafe-tables.index');
    }
}
