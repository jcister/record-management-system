import { ref } from 'vue';

export interface ToastOptions {
    title: string;
    description?: string;
    variant?: 'default' | 'destructive';
    duration?: number;
}

interface Toast extends ToastOptions {
    id: number;
    fadingOut?: boolean;
    timeoutId?: ReturnType<typeof setTimeout>;
}

const toasts = ref<Toast[]>([]);
let nextId = 0;

export function useToast() {
    const show = (options: ToastOptions) => {
        const toast: Toast = {
            ...options,
            id: nextId++,
            variant: options.variant || 'default',
            duration: options.duration || 3000,
            fadingOut: false,
        };

        toasts.value.push(toast);

        // Auto-remove after 1.5 seconds (skip if duration is 0 or negative)
        if (toast.duration !== 0) {
            const timeoutId = setTimeout(() => {
                remove(toast.id);
            }, 3000);
            toast.timeoutId = timeoutId;
        }

        return toast.id;
    };

    const update = (id: number, options: Partial<ToastOptions>) => {
        const index = toasts.value.findIndex(t => t.id === id);
        if (index !== -1) {
            const toast = toasts.value[index];

            // Clear existing timeout if updating
            if (toast.timeoutId) {
                clearTimeout(toast.timeoutId);
                toast.timeoutId = undefined;
            }

            // Update toast properties
            if (options.title !== undefined) {
                toast.title = options.title;
            }
            if (options.description !== undefined) {
                toast.description = options.description;
            }
            if (options.variant !== undefined) {
                toast.variant = options.variant;
            }
            if (options.duration !== undefined) {
                toast.duration = options.duration;
            }

            // Reset timeout if duration is not 0 (1.5 seconds auto-dismiss)
            if (toast.duration !== 0) {
                const timeoutId = setTimeout(() => {
                    remove(toast.id);
                }, 1500);
                toast.timeoutId = timeoutId;
            }
        }
    };

    const remove = (id: number) => {
        const index = toasts.value.findIndex(t => t.id === id);
        if (index !== -1) {
            const toast = toasts.value[index];
            // Start fade-out animation
            toast.fadingOut = true;

            // Remove after fade-out completes (2 seconds)
            setTimeout(() => {
                const fadeIndex = toasts.value.findIndex(t => t.id === id);
                if (fadeIndex !== -1) {
                    toasts.value.splice(fadeIndex, 1);
                }
            }, 2000);
        }
    };

    const error = (title: string, description?: string) => {
        return show({ title, description, variant: 'destructive' });
    };

    const success = (title: string, description?: string) => {
        return show({ title, description, variant: 'default' });
    };

    return {
        toasts,
        show,
        update,
        remove,
        error,
        success,
    };
}
