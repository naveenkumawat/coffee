<?php

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddOn\AddOnStoreRequest;
use App\Http\Requests\AddOn\AddOnUpdateRequest;
use App\Models\AddOn;
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
        ]);
    }

    public function store(AddOnStoreRequest $request): RedirectResponse
    {
        $payload = $request->safe()->except(['image', 'remove_image']);
        $addOn = $this->service->store($payload);
        $this->service->syncImage(
            $addOn,
            $request->file('image'),
            $request->boolean('remove_image'),
        );

        return redirect()
            ->route('administrator.add-ons.edit', $addOn)
            ->with('status', 'Add-on created successfully.');
    }

    public function edit(AddOn $addOn): View
    {
        $this->authorize('update', $addOn);

        return view('administrator.add-ons.edit', [
            'addOn' => $addOn,
            'productUsageCount' => $addOn->products()->count(),
        ]);
    }

    public function update(AddOnUpdateRequest $request, AddOn $addOn): RedirectResponse
    {
        $this->authorize('update', $addOn);

        $payload = $request->safe()->except(['image', 'remove_image']);
        $this->service->update($addOn, $payload);
        $this->service->syncImage(
            $addOn->fresh(),
            $request->file('image'),
            $request->boolean('remove_image'),
        );

        return redirect()
            ->route('administrator.add-ons.edit', $addOn)
            ->with('status', 'Add-on updated successfully.');
    }

    public function destroy(AddOn $addOn): RedirectResponse
    {
        $this->authorize('delete', $addOn);

        $addOn->forceFill(['is_active' => false])->save();
        $addOn->delete();

        return redirect()
            ->route('administrator.add-ons.index')
            ->with('status', 'Add-on archived successfully.');
    }
}
