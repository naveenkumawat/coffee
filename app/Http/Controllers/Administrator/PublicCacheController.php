<?php

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\Controller;
use App\Models\WebsiteSetting;
use App\Services\PublicCache\PublicCacheVersionServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PublicCacheController extends Controller
{
    public function __construct(
        protected PublicCacheVersionServiceInterface $publicCache,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', WebsiteSetting::class);

        return view('administrator.cache-management.index', [
            'publicCache' => $this->publicCache->snapshot(),
        ]);
    }

    public function clearServer(Request $request): RedirectResponse
    {
        $this->authorize('update', WebsiteSetting::class);

        $this->publicCache->forgetServerPublicCaches();

        return redirect()
            ->route('administrator.cache-management.index')
            ->with('status', 'Server application caches were cleared. Customer devices keep their copies until the public cache version changes.');
    }

    public function refreshCustomer(Request $request): RedirectResponse
    {
        $this->authorize('update', WebsiteSetting::class);

        $this->publicCache->invalidate('admin_refresh_customer', $request->user('admin')?->getKey());

        return redirect()
            ->route('administrator.cache-management.index')
            ->with('status', 'Customer cache version was refreshed. Devices will download the latest public content when they next connect.');
    }

    public function clearAll(Request $request): RedirectResponse
    {
        $this->authorize('update', WebsiteSetting::class);

        $this->publicCache->invalidate('admin_clear_all', $request->user('admin')?->getKey());

        return redirect()
            ->route('administrator.cache-management.index')
            ->with('status', 'Application and customer public caches were refreshed. Carts, orders, and sessions were not changed.');
    }
}
