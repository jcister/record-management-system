import { computed, onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

import { edit as editAppearance } from '@/routes/appearance';

export interface IconColorOption {
    key: string;
    label: string;
    value: string;
    swatch: string;
}

export const iconColorOptions: IconColorOption[] = [
    { key: 'default', label: 'Default', value: '', swatch: '' },
    { key: 'blue', label: 'Blue', value: '#3b82f6', swatch: '#3b82f6' },
    { key: 'indigo', label: 'Indigo', value: '#6366f1', swatch: '#6366f1' },
    { key: 'green', label: 'Green', value: '#22c55e', swatch: '#22c55e' },
    { key: 'orange', label: 'Orange', value: '#f97316', swatch: '#f97316' },
    { key: 'teal', label: 'Teal', value: '#14b8a6', swatch: '#14b8a6' },
    { key: 'rose', label: 'Rose', value: '#f43f5e', swatch: '#f43f5e' },
];

const STORAGE_KEY = 'sidebar-icon-color';

/** Resolve a stored value (legacy key name or hex) to a hex string. */
function resolveStoredValue(stored: string): string {
    if (!stored || stored === 'default') return '';
    const preset = iconColorOptions.find((o) => o.key === stored);
    return preset ? preset.value : stored;
}

export function applyIconColor(value: string): void {
    if (typeof window === 'undefined') return;

    if (!value) {
        document.documentElement.style.removeProperty('--sidebar-icon-color');
    } else {
        document.documentElement.style.setProperty('--sidebar-icon-color', value);
    }
}

export function initializeIconColor(): void {
    if (typeof window === 'undefined') return;

    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored) {
        applyIconColor(resolveStoredValue(stored));
    }
}

/** Module-level ref so all composable instances share the same reactive state. */
const iconColor = ref<string>('');

export function useIconColor() {
    onMounted(() => {
        const stored = localStorage.getItem(STORAGE_KEY);

        if (stored !== null) {
            const resolved = resolveStoredValue(stored);
            // Migrate legacy key-based values to hex on first encounter.
            if (resolved !== stored) {
                localStorage.setItem(STORAGE_KEY, resolved);
            }
            iconColor.value = resolved;
        } else {
            // Fall back to server-persisted value for cross-device sync.
            const page = usePage();
            const serverColor = (page.props as any).auth?.user?.sidebar_icon_color ?? '';
            if (serverColor) {
                iconColor.value = serverColor;
                localStorage.setItem(STORAGE_KEY, serverColor);
                applyIconColor(serverColor);
            }
        }
    });

    /** Returns the preset key that matches the current color, or 'custom' / 'default'. */
    const activePresetKey = computed<string>(() => {
        if (!iconColor.value) return 'default';
        const match = iconColorOptions.find((o) => o.value === iconColor.value);
        return match ? match.key : 'custom';
    });

    function updateIconColor(value: string): void {
        iconColor.value = value;
        localStorage.setItem(STORAGE_KEY, value);
        applyIconColor(value);

        router.patch(
            editAppearance.url(),
            { sidebar_icon_color: value || null },
            { preserveScroll: true, preserveState: true },
        );
    }

    return { iconColor, activePresetKey, updateIconColor, iconColorOptions };
}
