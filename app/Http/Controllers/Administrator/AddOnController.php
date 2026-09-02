<?php

namespace App\Http\Controllers\Administrator;

use App\Enums\IngredientUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\AddOn\AddOnStoreRequest;
use App\Http\Requests\AddOn\AddOnUpdateRequest;
use App\Models\AddOn;
use App\Models\Ingredient;
use App\Services\AddOn\AddOnServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AddOnController extends Controller
{
    public function __construct(
        protected AddOnServiceInterface $service,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', AddOn::class);

        return view('administrator.add-ons.index', [
            'addOns' => $this->service->paginateForAdmin($request->string('q')->toString() ?: ($request->string('search')->toString() ?: null)),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', AddOn::class);

        return view('administrator.add-ons.create', [
            'addOn' => new AddOn([
                'is_active' => true,
                'sort_order' => 10,
                'default_price' => '0.00',
            ]),
            'ingredientOptions' => $this->ingredientOptions(),
            'unitOptions' => IngredientUnit::options(),
            'lineRows' => [],
        ]);
    }

    public function store(AddOnStoreRequest $request): RedirectResponse
    {
        $addOn = $this->service->store($request->validated());

        return redirect()
            ->route('administrator.add-ons.edit', $addOn)
            ->with('status', 'Add-on created successfully.');
    }

    public function edit(AddOn $addOn): View
    {
        $this->authorize('update', $addOn);

        $addOn->load('recipeLines.ingredient');

        return view('administrator.add-ons.edit', [
            'addOn' => $addOn,
            'ingredientOptions' => $this->ingredientOptions(),
            'unitOptions' => IngredientUnit::options(),
            'lineRows' => $addOn->recipeLines->map(fn ($line): array => [
                'id' => $line->id,
                'ingredient_id' => $line->ingredient_id,
                'quantity' => (string) $line->quantity,
                'measurement_unit' => $line->measurement_unit instanceof \BackedEnum
                    ? $line->measurement_unit->value
                    : (string) $line->measurement_unit,
                'sort_order' => $line->sort_order,
            ])->all(),
        ]);
    }

    public function update(AddOnUpdateRequest $request, AddOn $addOn): RedirectResponse
    {
        $this->authorize('update', $addOn);

        $this->service->update($addOn, $request->validated());

        return redirect()
            ->route('administrator.add-ons.edit', $addOn)
            ->with('status', 'Add-on updated successfully.');
    }

    public function destroy(AddOn $addOn): RedirectResponse
    {
        $this->authorize('delete', $addOn);

        $addOn->delete();

        return redirect()
            ->route('administrator.add-ons.index')
            ->with('status', 'Add-on archived successfully.');
    }

    /**
     * @return array<int, string>
     */
    protected function ingredientOptions(): array
    {
        return Ingredient::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
