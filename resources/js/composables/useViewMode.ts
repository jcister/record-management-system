import { ref, watch, type Ref } from 'vue';

type ViewMode = 'tiles' | 'list';

const instances = new Map<string, Ref<ViewMode>>();

function getViewModeRef(storageKey: string) {
    if (!instances.has(storageKey)) {
        const initial: ViewMode = (localStorage.getItem(storageKey) as ViewMode) || 'list';
        const mode = ref<ViewMode>(initial);

        watch(mode, (v) => {
            try {
                localStorage.setItem(storageKey, v);
            } catch {
                // ignore
            }
        });

        instances.set(storageKey, mode);
    }

    return instances.get(storageKey)!;
}

export function useViewMode(storageKey = 'viewMode') {
    const viewMode = getViewModeRef(storageKey);

    const setViewMode = (mode: ViewMode) => {
        viewMode.value = mode;
    };

    const toggle = () => {
        viewMode.value = viewMode.value === 'tiles' ? 'list' : 'tiles';
    };

    return {
        viewMode,
        setViewMode,
        toggle,
    };
}
