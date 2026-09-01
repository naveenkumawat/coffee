<?php

namespace App\Http\Controllers\Administrator;

use App\Enums\ReferralStatus;
use App\Http\Controllers\Controller;
use App\Models\CustomerReferral;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', CustomerReferral::class);

        $query = CustomerReferral::query()
            ->with(['referrer', 'referred', 'qualifiedOrder', 'reward'])
            ->latest('id');

        if ($request->filled('status')) {
            $status = ReferralStatus::tryFrom((string) $request->string('status'));
            if ($status !== null) {
                $query->where('status', $status->value);
            }
        }

        if ($request->filled('q')) {
            $term = '%'.trim((string) $request->string('q')).'%';
            $query->where(function ($builder) use ($term): void {
                $builder->where('referral_code_snapshot', 'like', $term)
                    ->orWhereHas('referrer', fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term))
                    ->orWhereHas('referred', fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term));
            });
        }

        return view('administrator.referrals.index', [
            'referrals' => $query->paginate(25)->withQueryString(),
            'statuses' => ReferralStatus::cases(),
            'filters' => [
                'status' => $request->string('status')->toString(),
                'q' => $request->string('q')->toString(),
            ],
        ]);
    }
}
