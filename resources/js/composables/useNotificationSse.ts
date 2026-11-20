import type { NotificationSseEvent } from '@/types';
import { router } from '@inertiajs/vue3';
import { onBeforeUnmount } from 'vue';

type UseNotificationSseOptions = {
    onEvent: (event: NotificationSseEvent) => void;
};

const isNotificationSseEvent = (
    payload: unknown,
): payload is NotificationSseEvent => {
    if (typeof payload !== 'object' || payload === null) {
        return false;
    }

    const event = (payload as { event?: unknown }).event;

    return (
        event === 'notification_created' ||
        event === 'notification_read' ||
        event === 'notifications_all_read'
    );
};

export const useNotificationSse = ({
    onEvent,
}: UseNotificationSseOptions): {
    connect: () => void;
    disconnect: () => void;
} => {
    let eventSource: EventSource | null = null;
    let removeBeforeNavigateListener: (() => void) | null = null;

    const disconnect = (): void => {
        if (eventSource !== null) {
            eventSource.close();
            eventSource = null;
        }
    };

    const registerNavigateAwayDisconnect = (): void => {
        if (removeBeforeNavigateListener !== null) {
            return;
        }

        removeBeforeNavigateListener = router.on('before', () => {
            disconnect();
        });
    };

    const connect = (): void => {
        if (eventSource !== null) {
            return;
        }

        if (typeof window === 'undefined' || typeof EventSource === 'undefined') {
            return;
        }

        eventSource = new EventSource('/api/sse/notifications/updates');
        registerNavigateAwayDisconnect();

        eventSource.onmessage = (event): void => {
            try {
                const payload: unknown = JSON.parse(event.data);

                if (!isNotificationSseEvent(payload)) {
                    return;
                }

                onEvent(payload);
            } catch {
                // Ignore malformed messages and keep stream alive.
            }
        };

        eventSource.onerror = (): void => {
            // EventSource reconnects automatically.
        };
    };

    onBeforeUnmount(() => {
        removeBeforeNavigateListener?.();
        removeBeforeNavigateListener = null;
        disconnect();
    });

    return {
        connect,
        disconnect,
    };
};
