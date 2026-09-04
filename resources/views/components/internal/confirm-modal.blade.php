{{--
  Shared internal confirmation modal.
  Trigger via data-confirm attributes or InternalConfirm.open({...}).
--}}
<div class="modal fade" id="internalConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-450px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-4" id="internalConfirmTitle">Confirm</h2>
                <button type="button" class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal" aria-label="Cancel">
                    <i class="ki-outline ki-cross fs-1"></i>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-gray-700 mb-0" id="internalConfirmBody"></p>
                <div class="mt-5 d-none" id="internalConfirmReasonWrap">
                    <label class="form-label" for="internalConfirmReason" id="internalConfirmReasonLabel">Reason</label>
                    <textarea
                        id="internalConfirmReason"
                        class="form-control"
                        rows="3"
                        maxlength="500"
                        placeholder="Add a short note"
                    ></textarea>
                    <div class="invalid-feedback d-none" id="internalConfirmReasonError">A reason is required.</div>
                </div>
            </div>
            <div class="modal-footer flex-center gap-2">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal" id="internalConfirmCancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="internalConfirmSubmit">Confirm</button>
            </div>
        </div>
    </div>
</div>
