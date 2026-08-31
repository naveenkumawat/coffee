<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Enums\OrderStatus;
use App\Http\Controllers\Api\V1\Concerns\InteractsWithApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\OrderPaymentProofUploadRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Order;
use App\Repositories\Order\OrderRepositoryInterface;
use App\Services\Order\OrderServiceInterface;
use App\Services\Rating\ProductRatingServiceInterface;
use App\Services\WebsiteSetting\WebsiteSettingServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerOrderController extends Controller
{
    use InteractsWithApiResponses;

    public function __construct(
        protected OrderRepositoryInterface $orders,
        protected WebsiteSettingServiceInterface $websiteSettings,
        protected OrderServiceInterface $orderService,
        protected ProductRatingServiceInterface $ratings,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(50, (int) $request->integer('per_page', 10)));

        return $this->respondWithPaginator(
            $this->orders->paginateForCustomer($request->user(), $perPage),
            OrderResource::class,
            'Customer orders retrieved.',
        );
    }

    public function show(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $order->load(['items', 'statusHistory']);
        $this->attachRatingContext($order);

        return $this->respondWithResource(
            new OrderResource($order),
            'Customer order detail retrieved.',
            200,
            [
                'payment' => $this->websiteSettings->paymentInstructions(),
            ],
        );
    }

    public function uploadPaymentProof(OrderPaymentProofUploadRequest $request, Order $order): JsonResponse
    {
        $this->authorize('uploadPaymentProof', $order);

        $order = $this->orderService->uploadPaymentProof(
            $order,
            $request->user(),
            $request->file('payment_proof'),
        );

        return $this->respondWithResource(
            new OrderResource($order->loadMissing(['items', 'statusHistory'])),
            'Payment proof uploaded successfully.',
            200,
            [
                'payment' => $this->websiteSettings->paymentInstructions(),
            ],
        );
    }

    public function paymentProof(Order $order): StreamedResponse
    {
        $this->authorize('viewPaymentProof', $order);

        abort_unless($order->hasPaymentProof(), 404);

        $disk = $order->payment_proof_disk ?: 'local';
        $path = (string) $order->payment_proof_path;

        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->response(
            $path,
            basename($path),
            [
                'Content-Type' => $order->payment_proof_mime ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.basename($path).'"',
            ],
        );
    }

    protected function attachRatingContext(Order $order): void
    {
        $customer = $order->customer;
        $isCompleted = $order->status === OrderStatus::Completed;

        if (! $customer || ! $isCompleted) {
            return;
        }

        $productIds = $order->items
            ->pluck('product_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            return;
        }

        $ratingsByProductId = $customer->productRatings()
            ->whereIn('product_id', $productIds->all())
            ->get()
            ->keyBy(fn ($rating) => (int) $rating->product_id);

        foreach ($order->items as $item) {
            if ($item->product_id === null) {
                continue;
            }

            $productId = (int) $item->product_id;
            $existing = $ratingsByProductId->get($productId);

            $item->setRelation('myRating', $existing);
            $item->setAttribute('can_rate', true);
        }
    }
}
