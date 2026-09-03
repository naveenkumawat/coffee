import chimeUrl from '../../sounds/notification-chime.wav';

export function createNotificationSoundManager() {
    /** @type {HTMLAudioElement|null} */
    let audio = null;
    let unlocked = false;
    let playing = false;

    function ensureAudio() {
        if (audio) {
            return audio;
        }

        audio = new Audio(chimeUrl);
        audio.preload = 'auto';
        audio.volume = 0.55;

        return audio;
    }

    function unlock() {
        if (unlocked) {
            return;
        }

        unlocked = true;

        try {
            const el = ensureAudio();
            el.muted = true;
            const playPromise = el.play();
            if (playPromise && typeof playPromise.then === 'function') {
                playPromise
                    .then(() => {
                        el.pause();
                        el.currentTime = 0;
                        el.muted = false;
                    })
                    .catch(() => {
                        el.muted = false;
                    });
            }
        } catch {
            // Autoplay may remain blocked; visual alerts still work.
        }
    }

    function bindUnlock() {
        const once = () => {
            unlock();
            window.removeEventListener('pointerdown', once);
            window.removeEventListener('keydown', once);
            window.removeEventListener('touchstart', once);
        };

        window.addEventListener('pointerdown', once, { passive: true });
        window.addEventListener('keydown', once);
        window.addEventListener('touchstart', once, { passive: true });
    }

    bindUnlock();

    return {
        unlock,
        async play() {
            if (playing) {
                return;
            }

            try {
                const el = ensureAudio();
                playing = true;
                el.currentTime = 0;
                await el.play();
            } catch {
                // Ignore blocked audio.
            } finally {
                window.setTimeout(() => {
                    playing = false;
                }, 250);
            }
        },
    };
}
