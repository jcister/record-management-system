import { ref } from 'vue';

type EmitFn = (...args: any[]) => void;

export function useLongPress(emit: EmitFn, duration = 500) {
    const longPressTimer = ref<number | null>(null);
    const longPressed = ref(false);
    let lastTouchTime = 0;

    const isSimulatedMouseEvent = (event?: Event): boolean => {
        if (!event) return false;
        return event.type.startsWith('mouse') && Date.now() - lastTouchTime < 700;
    };

    const startPress = (event?: Event) => {
        if (event?.type?.startsWith('touch')) {
            lastTouchTime = Date.now();
        } else if (isSimulatedMouseEvent(event)) {
            return;
        }

        longPressed.value = false;
        if (longPressTimer.value) window.clearTimeout(longPressTimer.value);
        longPressTimer.value = window.setTimeout(() => {
            longPressed.value = true;
            emit('longpress');
        }, duration) as unknown as number;
        if (event) event.stopPropagation();
    };

    const cancelPress = (event?: Event) => {
        if (event?.type?.startsWith('touch')) {
            lastTouchTime = Date.now();
        } else if (isSimulatedMouseEvent(event)) {
            return;
        }

        if (longPressTimer.value) {
            clearTimeout(longPressTimer.value);
            longPressTimer.value = null;
        }
        if (event) event.stopPropagation();
    };

    const handleClick = (e: MouseEvent) => {
        if (longPressed.value) {
            longPressed.value = false;
            return;
        }
        emit('click', e);
    };

    const handleDblClick = (e: MouseEvent) => {
        if (longPressed.value) {
            longPressed.value = false;
            return;
        }
        emit('dblclick', e);
    };

    return { longPressTimer, longPressed, startPress, cancelPress, handleClick, handleDblClick };
}
