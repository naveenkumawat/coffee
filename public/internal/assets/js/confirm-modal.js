/**
 * Shared designed confirmation for internal Blade panels.
 * Replaces browser confirm() for business actions.
 */
(function (window, document) {
    'use strict';

    var modalEl = null;
    var modal = null;
    var pendingResolve = null;
    var pendingOptions = null;

    function qs(id) {
        return document.getElementById(id);
    }

    function ensureModal() {
        if (modalEl) {
            return !!modal;
        }

        modalEl = qs('internalConfirmModal');
        if (!modalEl || typeof bootstrap === 'undefined') {
            return false;
        }

        modal = bootstrap.Modal.getOrCreateInstance(modalEl);

        qs('internalConfirmSubmit').addEventListener('click', function () {
            if (!pendingOptions) {
                return;
            }

            var reason = '';
            if (pendingOptions.requireReason) {
                var reasonInput = qs('internalConfirmReason');
                reason = (reasonInput.value || '').trim();
                var error = qs('internalConfirmReasonError');

                if (!reason) {
                    reasonInput.classList.add('is-invalid');
                    error.classList.remove('d-none');
                    return;
                }

                reasonInput.classList.remove('is-invalid');
                error.classList.add('d-none');
            }

            var resolve = pendingResolve;
            pendingResolve = null;
            pendingOptions = null;
            modal.hide();
            resolve({ confirmed: true, reason: reason || null });
        });

        modalEl.addEventListener('hidden.bs.modal', function () {
            if (pendingResolve) {
                var resolve = pendingResolve;
                pendingResolve = null;
                pendingOptions = null;
                resolve({ confirmed: false, reason: null });
            }

            qs('internalConfirmSubmit').disabled = false;
            qs('internalConfirmCancel').disabled = false;
        });

        return true;
    }

    function open(options) {
        if (!ensureModal()) {
            return Promise.resolve({ confirmed: false, reason: null });
        }

        return new Promise(function (resolve) {
            pendingResolve = resolve;
            pendingOptions = options || {};

            qs('internalConfirmTitle').textContent = options.title || 'Confirm';
            qs('internalConfirmBody').textContent = options.body || '';
            qs('internalConfirmCancel').textContent = options.cancelLabel || 'Cancel';

            var submit = qs('internalConfirmSubmit');
            submit.textContent = options.confirmLabel || 'Confirm';
            submit.className = 'btn ' + (options.confirmClass || 'btn-primary');
            submit.disabled = false;
            qs('internalConfirmCancel').disabled = false;

            var reasonWrap = qs('internalConfirmReasonWrap');
            var reasonInput = qs('internalConfirmReason');
            reasonInput.value = '';
            reasonInput.classList.remove('is-invalid');
            qs('internalConfirmReasonError').classList.add('d-none');

            if (options.requireReason) {
                reasonWrap.classList.remove('d-none');
                qs('internalConfirmReasonLabel').textContent = options.reasonLabel || 'Reason';
                reasonInput.placeholder = options.reasonPlaceholder || 'Add a short note';
            } else {
                reasonWrap.classList.add('d-none');
            }

            modal.show();
        });
    }

    function bindDocument() {
        document.addEventListener(
            'submit',
            function (event) {
                var form = event.target;
                if (!(form instanceof HTMLFormElement)) {
                    return;
                }

                var title = form.getAttribute('data-confirm-title');
                var body = form.getAttribute('data-confirm-body');
                var legacy = form.getAttribute('data-confirm');

                if (!title && !body && !legacy) {
                    return;
                }

                if (form.dataset.confirmAccepted === '1') {
                    form.dataset.confirmAccepted = '0';
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                open({
                    title: title || 'Confirm',
                    body: body || legacy || 'Continue with this action?',
                    confirmLabel: form.getAttribute('data-confirm-label') || 'Confirm',
                    confirmClass: form.getAttribute('data-confirm-class') || 'btn-primary',
                    requireReason: form.getAttribute('data-confirm-require-reason') === '1',
                    reasonLabel: form.getAttribute('data-confirm-reason-label') || 'Reason',
                }).then(function (result) {
                    if (!result.confirmed) {
                        return;
                    }

                    var reasonField = form.getAttribute('data-confirm-reason-field') || 'note';
                    if (result.reason) {
                        var existing = form.querySelector('[name="' + reasonField + '"]');
                        if (existing) {
                            existing.value = result.reason;
                        } else {
                            var input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = reasonField;
                            input.value = result.reason;
                            form.appendChild(input);
                        }
                    }

                    form.dataset.confirmAccepted = '1';
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
                });
            },
            true,
        );
    }

    window.InternalConfirm = { open: open };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindDocument);
    } else {
        bindDocument();
    }
})(window, document);
