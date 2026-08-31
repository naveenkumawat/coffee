<?php

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rating\ProductRatingAdminIndexRequest;
use App\Models\Product;
use App\Models\ProductRating;
use App\Services\Rating\ProductRatingServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ProductRatingController extends Controller
{
    public function __construct(
        protected ProductRatingServiceInterface $ratings,
    ) {}

    public function index(ProductRatingAdminIndexRequest $request): View
    {
        $this->authorize('viewAny', ProductRating::class);

        return view('administrator.products.ratings.index', [
            'ratings' => $this->ratings->paginateForAdmin($request->filters()),
            'productOptions' => Product::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function show(ProductRating $productRating): View
    {
        $this->authorize('view', $productRating);

        $productRating->load([
            'product:id,name,slug',
            'customer:id,name,email',
            'qualifyingOrder:id,order_number,completed_at,status',
            'moderator:id,name',
        ]);

        return view('administrator.products.ratings.show', [
            'rating' => $productRating,
        ]);
    }

    public function hide(ProductRating $productRating): RedirectResponse
    {
        $this->authorize('moderate', ProductRating::class);

        $this->ratings->hideReview($productRating, request()->user('admin'));

        return redirect()
            ->route('administrator.products.ratings.show', $productRating)
            ->with('status', 'Review text hidden from the public catalog. The star rating still counts toward the product average.');
    }

    public function publish(ProductRating $productRating): RedirectResponse
    {
        $this->authorize('moderate', ProductRating::class);

        $this->ratings->publishReview($productRating, request()->user('admin'));

        return redirect()
            ->route('administrator.products.ratings.show', $productRating)
            ->with('status', 'Review published to the public catalog.');
    }

    public function destroy(ProductRating $productRating): RedirectResponse
    {
        $this->authorize('delete', $productRating);

        $this->ratings->deleteAsAdmin($productRating);

        return redirect()
            ->route('administrator.products.ratings.index')
            ->with('status', 'Rating deleted. Score and review were removed from aggregates.');
    }
}
