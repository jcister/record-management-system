import { ref } from 'vue';

export interface ConfirmOptions {
    title: string;
    description: string;
    confirmText?: string;
    cancelText?: string;
    replaceText?: string; // New option for replace
    variant?: 'default' | 'destructive';
}

const isOpen = ref(false);
const options = ref<ConfirmOptions>({
    title: '',
    description: '',
    confirmText: 'Continue',
    cancelText: 'Cancel',
    variant: 'default',
});
let resolvePromise: ((value: 'confirm' | 'replace' | 'cancel') => void) | null = null;

export function useConfirm() {
    const confirm = (opts: ConfirmOptions): Promise<'confirm' | 'replace' | 'cancel'> => {
        options.value = {
            ...opts,
            confirmText: opts.confirmText || 'Continue',
            cancelText: opts.cancelText || 'Cancel',
            variant: opts.variant || 'default',
        };
        isOpen.value = true;

        return new Promise<'confirm' | 'replace' | 'cancel'>((resolve) => {
            resolvePromise = resolve;
        });
    };

    const handleConfirm = () => {
        isOpen.value = false;
        if (resolvePromise) {
            resolvePromise('confirm');
            resolvePromise = null;
        }
    };

    const handleReplace = () => {
        isOpen.value = false;
        if (resolvePromise) {
            resolvePromise('replace');
            resolvePromise = null;
        }
    };

    const handleCancel = () => {
        isOpen.value = false;
        if (resolvePromise) {
            resolvePromise('cancel');
            resolvePromise = null;
        }
    };

    return {
        isOpen,
        options,
        confirm,
        handleConfirm,
        handleReplace,
        handleCancel,
    };
}
