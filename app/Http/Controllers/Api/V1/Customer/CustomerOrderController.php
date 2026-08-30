<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Api\V1\Concerns\InteractsWithApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\OrderPaymentProofUploadRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Order;
use App\Repositories\Order\OrderRepositoryInterface;
use App\Services\Order\OrderServiceInterface;
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

        return $this->respondWithResource(
            new OrderResource($order->load(['items', 'statusHistory'])),
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
}
