<?php

namespace App\Http\Controllers\Administrator;

use App\Enums\SocialIconKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\SocialLink\SocialLinkStoreRequest;
use App\Http\Requests\SocialLink\SocialLinkUpdateRequest;
use App\Models\SocialLink;
use App\Repositories\Social\SocialLinkRepositoryInterface;
use App\Services\Social\SocialLinkServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class SocialLinkController extends Controller
{
    public function __construct(
        protected SocialLinkRepositoryInterface $links,
        protected SocialLinkServiceInterface $service,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', SocialLink::class);

        return view('administrator.social-links.index', [
            'links' => $this->links->paginateForAdmin(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', SocialLink::class);

        return view('administrator.social-links.create', [
            'link' => new SocialLink([
                'is_active' => false,
                'sort_order' => 100,
                'icon_key' => SocialIconKey::Facebook->value,
            ]),
            'iconOptions' => SocialIconKey::options(),
        ]);
    }

    public function store(SocialLinkStoreRequest $request): RedirectResponse
    {
        $link = $this->service->store($request->validated());

        return redirect()
            ->route('administrator.social-links.edit', $link)
            ->with('status', 'Social link created successfully.');
    }

    public function edit(SocialLink $socialLink): View
    {
        $this->authorize('update', $socialLink);

        return view('administrator.social-links.edit', [
            'link' => $socialLink,
            'iconOptions' => SocialIconKey::options(),
        ]);
    }

    public function update(SocialLinkUpdateRequest $request, SocialLink $socialLink): RedirectResponse
    {
        $this->authorize('update', $socialLink);

        $this->service->update($socialLink, $request->validated());

        return redirect()
            ->route('administrator.social-links.edit', $socialLink)
            ->with('status', 'Social link updated successfully.');
    }

    public function destroy(SocialLink $socialLink): RedirectResponse
    {
        $this->authorize('delete', $socialLink);

        $this->service->delete($socialLink);

        return redirect()
            ->route('administrator.social-links.index')
            ->with('status', 'Social link archived successfully.');
    }

    public function toggle(SocialLink $socialLink): RedirectResponse
    {
        $this->authorize('update', $socialLink);

        $this->service->setActive($socialLink, ! $socialLink->is_active);

        return redirect()
            ->route('administrator.social-links.index')
            ->with('status', $socialLink->fresh()?->is_active ? 'Social link activated.' : 'Social link deactivated.');
    }

    public function moveUp(SocialLink $socialLink): RedirectResponse
    {
        $this->authorize('update', $socialLink);

        $this->service->move($socialLink, 'up');

        return redirect()->route('administrator.social-links.index');
    }

    public function moveDown(SocialLink $socialLink): RedirectResponse
    {
        $this->authorize('update', $socialLink);

        $this->service->move($socialLink, 'down');

        return redirect()->route('administrator.social-links.index');
    }
}
