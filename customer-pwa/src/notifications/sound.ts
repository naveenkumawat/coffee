import chimeUrl from '../assets/notification-chime.wav';

export function createNotificationSoundManager() {
  let audio: HTMLAudioElement | null = null;
  let unlocked = false;
  let playing = false;

  function ensureAudio(): HTMLAudioElement {
    if (!audio) {
      audio = new Audio(chimeUrl);
      audio.preload = 'auto';
      audio.volume = 0.55;
    }

    return audio;
  }

  function unlock(): void {
    if (unlocked) {
      return;
    }
    unlocked = true;
    try {
      const el = ensureAudio();
      el.muted = true;
      void el.play().then(() => {
        el.pause();
        el.currentTime = 0;
        el.muted = false;
      }).catch(() => {
        el.muted = false;
      });
    } catch {
      // ignore
    }
  }

  const once = (): void => {
    unlock();
    window.removeEventListener('pointerdown', once);
    window.removeEventListener('keydown', once);
  };
  window.addEventListener('pointerdown', once, { passive: true });
  window.addEventListener('keydown', once);

  return {
    unlock,
    async play(): Promise<void> {
      if (playing) {
        return;
      }
      try {
        const el = ensureAudio();
        playing = true;
        el.currentTime = 0;
        await el.play();
      } catch {
        // ignore blocked audio
      } finally {
        window.setTimeout(() => {
          playing = false;
        }, 250);
      }
    },
  };
}
