<?php

namespace App\Http\Controllers\Administrator;

use App\Enums\CafeClosureType;
use App\Http\Controllers\Controller;
use App\Http\Requests\CafeSchedule\CafeClosureStoreRequest;
use App\Http\Requests\CafeSchedule\CafeClosureUpdateRequest;
use App\Http\Requests\CafeSchedule\CafeOperatingHoursUpdateRequest;
use App\Http\Requests\CafeSchedule\CafeOrderingCloseRequest;
use App\Models\CafeClosure;
use App\Services\CafeAvailability\CafeAvailabilityServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CafeScheduleController extends Controller
{
    public function __construct(
        protected CafeAvailabilityServiceInterface $availability,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', CafeClosure::class);

        $status = $this->availability->status();
        $canManage = request()->user('admin')?->canManageWebsiteSettings() ?? false;

        return view('administrator.cafe-schedule.index', [
            'status' => $status,
            'weeklyHours' => $status->weeklyHours,
            'closures' => CafeClosure::query()->latest('starts_at')->paginate(20),
            'canManage' => $canManage,
            'timezone' => $this->availability->timezone(),
        ]);
    }

    public function editHours(): View
    {
        $this->authorize('create', CafeClosure::class);

        return view('administrator.cafe-schedule.hours', [
            'weeklyHours' => $this->availability->weeklySchedule(),
            'timezone' => $this->availability->timezone(),
        ]);
    }

    public function updateHours(CafeOperatingHoursUpdateRequest $request): RedirectResponse
    {
        $this->authorize('create', CafeClosure::class);

        $days = [];

        foreach ($request->validated('days') as $weekday => $day) {
            if (! ($day['enabled'] ?? false)) {
                $days[(int) $weekday] = [];

                continue;
            }

            $days[(int) $weekday] = [[
                'opens_at' => $day['opens_at'],
                'closes_at' => $day['closes_at'],
            ]];
        }

        $this->availability->syncWeeklyHours($days);

        return redirect()
            ->route('administrator.cafe-schedule.index')
            ->with('status', 'Weekly operating hours updated.');
    }

    public function createClosure(): View
    {
        $this->authorize('create', CafeClosure::class);

        return view('administrator.cafe-schedule.closures-form', [
            'closure' => new CafeClosure([
                'type' => CafeClosureType::Holiday,
                'is_active' => true,
            ]),
            'typeOptions' => CafeClosureType::options(),
            'timezone' => $this->availability->timezone(),
            'action' => route('administrator.cafe-schedule.closures.store'),
            'method' => 'POST',
            'submit' => 'Create closure',
        ]);
    }

    public function storeClosure(CafeClosureStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', CafeClosure::class);

        $this->availability->storeClosure($request->validated());

        return redirect()
            ->route('administrator.cafe-schedule.index')
            ->with('status', 'Closure scheduled.');
    }

    public function editClosure(CafeClosure $cafeClosure): View
    {
        $this->authorize('update', $cafeClosure);

        return view('administrator.cafe-schedule.closures-form', [
            'closure' => $cafeClosure,
            'typeOptions' => CafeClosureType::options(),
            'timezone' => $this->availability->timezone(),
            'action' => route('administrator.cafe-schedule.closures.update', $cafeClosure),
            'method' => 'PUT',
            'submit' => 'Save closure',
        ]);
    }

    public function updateClosure(CafeClosureUpdateRequest $request, CafeClosure $cafeClosure): RedirectResponse
    {
        $this->authorize('update', $cafeClosure);

        $this->availability->updateClosure($cafeClosure, $request->validated());

        return redirect()
            ->route('administrator.cafe-schedule.index')
            ->with('status', 'Closure updated.');
    }

    public function toggleClosure(CafeClosure $cafeClosure): RedirectResponse
    {
        $this->authorize('update', $cafeClosure);

        $this->availability->setClosureActive($cafeClosure, ! $cafeClosure->is_active);

        return redirect()
            ->route('administrator.cafe-schedule.index')
            ->with('status', $cafeClosure->fresh()->is_active ? 'Closure activated.' : 'Closure deactivated.');
    }

    public function destroyClosure(CafeClosure $cafeClosure): RedirectResponse
    {
        $this->authorize('delete', $cafeClosure);

        $this->availability->archiveClosure($cafeClosure);

        return redirect()
            ->route('administrator.cafe-schedule.index')
            ->with('status', 'Closure archived.');
    }

    public function closeOrdering(CafeOrderingCloseRequest $request): RedirectResponse
    {
        $this->authorize('create', CafeClosure::class);

        $until = null;

        if ($request->validated('mode') === 'until') {
            $until = CarbonImmutable::parse(
                (string) $request->validated('closed_until'),
                $this->availability->timezone(),
            );
        }

        $this->availability->closeOrdering(
            $until,
            $request->validated('customer_message'),
        );

        return redirect()
            ->back()
            ->with('status', 'Café ordering is out of service.');
    }

    public function reopenOrdering(Request $request): RedirectResponse
    {
        $this->authorize('create', CafeClosure::class);

        $this->availability->reopenOrdering();

        return redirect()
            ->back()
            ->with('status', 'Café ordering has been reopened.');
    }
}
