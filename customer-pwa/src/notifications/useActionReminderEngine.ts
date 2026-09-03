import { useEffect, useRef } from 'react';
import { OperationalNotificationItem } from '../api/notifications';
import { REMINDER_INTERVAL_MS } from './config';
import { isReminderEligible, sortActionRequired } from './normalize';
import { createNotificationSoundManager } from './sound';
import { createTabLeader } from './tabLeader';
import { useNotificationStore } from '../stores/notificationStore';
import { useToastStore } from '../stores/toastStore';

/**
 * Leader-only 30s reminder engine for actionable operational notifications.
 */
export function useActionReminderEngine(enabled: boolean): void {
  const items = useNotificationStore((state) => state.items);
  const recordReminder = useNotificationStore((state) => state.recordReminder);
  const scheduleRef = useRef(new Map<number, number>());
  const soundRef = useRef<ReturnType<typeof createNotificationSoundManager> | null>(null);
  const leaderRef = useRef<ReturnType<typeof createTabLeader> | null>(null);

  useEffect(() => {
    if (!enabled) {
      return;
    }

    soundRef.current = createNotificationSoundManager();
    leaderRef.current = createTabLeader();

    const timer = window.setInterval(() => {
      const leader = leaderRef.current;
      const sound = soundRef.current;
      if (!leader?.isLeader()) {
        return;
      }

      const live = sortActionRequired(
        useNotificationStore.getState().items.filter(isReminderEligible),
      );
      const liveIds = new Set(live.map((item) => item.recipient_id));
      const schedule = scheduleRef.current;

      [...schedule.keys()].forEach((id) => {
        if (!liveIds.has(id)) {
          schedule.delete(id);
        }
      });

      live.forEach((item) => {
        if (!schedule.has(item.recipient_id)) {
          schedule.set(item.recipient_id, Date.now() + REMINDER_INTERVAL_MS);
        }
      });

      const now = Date.now();
      live.forEach((item) => {
        const due = schedule.get(item.recipient_id) ?? 0;
        if (due > now) {
          return;
        }

        useToastStore.getState().push(`Reminder: ${item.title}`, 'info', 5000);
        void sound?.play();
        void recordReminder(item.recipient_id);
        schedule.set(item.recipient_id, Date.now() + REMINDER_INTERVAL_MS);
      });
    }, 1000);

    return () => {
      window.clearInterval(timer);
      leaderRef.current?.destroy();
      scheduleRef.current.clear();
    };
  }, [enabled, recordReminder]);

  useEffect(() => {
    if (!enabled) {
      return;
    }

    const schedule = scheduleRef.current;
    items.filter(isReminderEligible).forEach((item: OperationalNotificationItem) => {
      if (!schedule.has(item.recipient_id)) {
        schedule.set(item.recipient_id, Date.now() + REMINDER_INTERVAL_MS);
      }
    });
  }, [enabled, items]);
}

export function presentImmediateAlert(item: OperationalNotificationItem, isLeader: boolean): void {
  if (!isLeader) {
    return;
  }

  useToastStore.getState().push(item.title, item.action_required ? 'info' : 'success', item.action_required ? 7000 : 4000);
}
