import { onMounted, onUnmounted, ref } from 'vue';

export function usePointerCapabilities() {
    const isTouchDevice = ref(false);
    let mediaQuery: MediaQueryList | null = null;

    const handleChange = (event: MediaQueryListEvent) => {
        isTouchDevice.value = event.matches;
    };

    onMounted(() => {
        if (typeof window === 'undefined') {
            return;
        }

        mediaQuery = window.matchMedia('(pointer: coarse)');
        isTouchDevice.value = mediaQuery.matches;
        mediaQuery.addEventListener('change', handleChange);
    });

    onUnmounted(() => {
        mediaQuery?.removeEventListener('change', handleChange);
    });

    return { isTouchDevice } as const;
}
