<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Api\V1\Concerns\InteractsWithApiResponses;
use App\Http\Controllers\Controller;
use App\Services\Referral\ReferralServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerReferralController extends Controller
{
    use InteractsWithApiResponses;

    public function __construct(
        protected ReferralServiceInterface $referrals,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $customer = $request->user();
        $code = $this->referrals->ensureCustomerReferralCode($customer);
        $settings = $this->referrals->settings();

        return $this->respondWithData([
            'enabled' => (bool) ($settings['enabled'] ?? false),
            'referral_code' => $code,
            'share_url' => $this->referrals->shareUrl($customer),
            'customer_message' => $settings['customer_message'] ?? null,
            'stats' => $this->referrals->customerStats($customer),
        ], 'Referral summary retrieved.');
    }
}
