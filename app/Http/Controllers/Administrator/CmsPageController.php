<?php

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\CmsPageUpdateRequest;
use App\Models\CmsPage;
use App\Services\Cms\CmsPageServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class CmsPageController extends Controller
{
    public function __construct(
        protected CmsPageServiceInterface $pages,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', CmsPage::class);

        return view('administrator.cms-pages.index', [
            'pages' => $this->pages->listForAdmin(),
            'customerBaseUrl' => rtrim((string) config('coffee.pwa.url'), '/'),
        ]);
    }

    public function edit(CmsPage $cmsPage): View
    {
        $this->authorize('update', $cmsPage);

        $pageKey = $cmsPage->pageKey();

        if ($pageKey === null) {
            abort(404);
        }

        return view('administrator.cms-pages.edit', [
            'page' => $this->pages->pageForAdmin($pageKey),
            'customerPreviewUrl' => rtrim((string) config('coffee.pwa.url'), '/').$pageKey->customerPath(),
        ]);
    }

    public function update(CmsPageUpdateRequest $request, CmsPage $cmsPage): RedirectResponse
    {
        $this->authorize('update', $cmsPage);

        $this->pages->update($cmsPage, $request->validated());

        return redirect()
            ->route('administrator.cms-pages.edit', $cmsPage)
            ->with('status', $cmsPage->title.' saved successfully.');
    }
}
