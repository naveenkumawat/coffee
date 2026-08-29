@php
    $messages = [];

    if ($message = session('success')) {
        $messages[] = ['type' => 'success', 'text' => $message];
    }

    if ($message = session('error')) {
        $messages[] = ['type' => 'danger', 'text' => $message];
    }

    if ($message = session('flashSuccess')) {
        $messages[] = ['type' => 'success', 'text' => $message];
    }

    if ($message = session('flashError')) {
        $messages[] = ['type' => 'danger', 'text' => $message];
    }

    if ($message = session('flashInfo')) {
        $messages[] = ['type' => 'info', 'text' => $message];
    }

    if ($message = session('flashWarning')) {
        $messages[] = ['type' => 'warning', 'text' => $message];
    }
@endphp

<div id="flash-toast-container" class="position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>

@if (count($messages) > 0)
    <script>
        (function () {
            var messages = @json($messages);

            function escapeHtml(text) {
                var element = document.createElement('div');
                element.textContent = text;

                return element.innerHTML;
            }

            function showFlashToasts() {
                var container = document.getElementById('flash-toast-container');

                if (!container) {
                    return;
                }

                var Bootstrap = window.bootstrap || window.Bootstrap;

                if (!Bootstrap || !Bootstrap.Toast) {
                    messages.forEach(function (message) {
                        alert(message.text);
                    });

                    return;
                }

                messages.forEach(function (message) {
                    var toastEl = document.createElement('div');
                    toastEl.className = 'toast align-items-center text-bg-' + message.type + ' border-0';
                    toastEl.setAttribute('role', 'alert');
                    toastEl.setAttribute('data-bs-animation', 'true');
                    toastEl.innerHTML =
                        '<div class="d-flex">' +
                            '<div class="toast-body">' + escapeHtml(message.text) + '</div>' +
                            '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>' +
                        '</div>';

                    container.appendChild(toastEl);

                    var toast = new Bootstrap.Toast(toastEl, {
                        delay: 5000,
                        autohide: false,
                        animation: true,
                    });

                    var timeoutId = null;
                    var remainingTime = 5000;
                    var startTime = Date.now();
                    var isPaused = false;

                    function startAutohide() {
                        if (isPaused) {
                            return;
                        }

                        if (timeoutId) {
                            clearTimeout(timeoutId);
                        }

                        startTime = Date.now();
                        timeoutId = setTimeout(function () {
                            if (!isPaused) {
                                toast.hide();
                            }
                        }, remainingTime);
                    }

                    toastEl.addEventListener('mouseenter', function () {
                        if (timeoutId) {
                            var elapsed = Date.now() - startTime;
                            remainingTime = Math.max(0, remainingTime - elapsed);
                            clearTimeout(timeoutId);
                            timeoutId = null;
                        }

                        isPaused = true;
                    });

                    toastEl.addEventListener('mouseleave', function () {
                        isPaused = false;

                        if (remainingTime > 0) {
                            startAutohide();
                        }
                    });

                    toast.show();
                    startAutohide();

                    toastEl.addEventListener('hidden.bs.toast', function () {
                        if (timeoutId) {
                            clearTimeout(timeoutId);
                        }

                        toastEl.remove();
                    });
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', showFlashToasts);
            } else {
                showFlashToasts();
            }
        })();
    </script>
@endif
