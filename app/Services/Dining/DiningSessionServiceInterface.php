<?php

namespace App\Services\Dining;

use App\Models\CafeTable;
use App\Models\DiningRoundDraft;
use App\Models\DiningSession;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

interface DiningSessionServiceInterface
{
    /**
     * @param  array{guest_count?: int|null}  $options
     */
    public function startSession(
        CafeTable $table,
        ?User $customer = null,
        ?User $openedBy = null,
        array $options = [],
    ): DiningSession;

    public function addDraftItem(
        DiningSession $session,
        int $productVariantId,
        int $quantity,
        ?User $customer = null,
    ): DiningRoundDraft;

    public function updateDraftItem(
        DiningSession $session,
        DiningRoundDraft $draft,
        int $quantity,
    ): DiningRoundDraft;

    public function removeDraftItem(DiningSession $session, DiningRoundDraft $draft): void;

    public function clearDrafts(DiningSession $session): void;

    public function placeRound(DiningSession $session, User $actor, ?string $customerNotes = null): Order;

    /**
     * @return array{
     *     subtotal: string,
     *     discount: string,
     *     taxable: string,
     *     tax: string,
     *     total: string,
     *     tax_enabled: bool,
     *     tax_label: ?string,
     *     tax_percent: ?string,
     *     tax_inclusive: bool,
     *     rounds: list<array{order_id: int, round_number: int, status: string, subtotal: string, total: string}>
     * }
     */
    public function runningBill(DiningSession $session): array;

    public function requestBill(DiningSession $session, User $actor): DiningSession;

    public function generateFinalBill(DiningSession $session, User $actor): DiningSession;

    public function setPaymentMethod(DiningSession $session, string $paymentMethodApiKey): DiningSession;

    public function uploadPaymentProof(DiningSession $session, User $actor, UploadedFile $file): DiningSession;

    public function confirmPayment(DiningSession $session, User $actor): DiningSession;

    public function markCashReceived(DiningSession $session, User $actor): DiningSession;

    public function closeSession(DiningSession $session, User $actor): DiningSession;

    public function reopenSession(DiningSession $session, User $actor, ?string $note = null): DiningSession;

    /**
     * @return Collection<int, array{table: CafeTable, state: string, session: ?DiningSession}>
     */
    public function tableOperationalStates(): Collection;

    public function findActiveForCustomer(User $customer): ?DiningSession;

    public function findActiveForTable(CafeTable $table): ?DiningSession;
}
