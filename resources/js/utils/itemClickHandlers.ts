import type { SelectionIdentity, SelectionType } from './selection';

interface ClickHandlerOptions {
    selectMode: boolean;
    isTouchDevice?: boolean;
    isSelected: (identity: SelectionIdentity) => boolean;
    toggleSelection: (identity: SelectionIdentity) => void;
    toggleSelectMode: () => void;
    onOpen: (id: string) => void;
    selectionCount?: number;
}

/**
 * Handle item click with select mode and mobile detection
 */
export function createItemClickHandler({
    selectMode,
    isTouchDevice = false,
    isSelected,
    toggleSelection,
    toggleSelectMode,
    onOpen,
    selectionCount = 0,
}: ClickHandlerOptions) {
    return (id: string, type: SelectionType, event?: Event): void => {
        if (event) {
            event.stopPropagation();
        }

        const identity: SelectionIdentity = { id, type };

        if (isTouchDevice) {
            if (selectMode) {
                const wasSelected = isSelected(identity);
                toggleSelection(identity);

                if (wasSelected && selectionCount <= 1) {
                    toggleSelectMode();
                }
            } else {
                onOpen(id);
            }
            return;
        }

        toggleSelection(identity);
    };
}

interface LongPressHandlerOptions {
    selectMode: boolean;
    isTouchDevice?: boolean;
    isSelected: (identity: SelectionIdentity) => boolean;
    toggleSelection: (identity: SelectionIdentity) => void;
    toggleSelectMode: () => void;
    selectionCount?: number;
}

/**
 * Handle long press to enter select mode
 */
export function createLongPressHandler({
    selectMode,
    isTouchDevice = false,
    isSelected,
    toggleSelection,
    toggleSelectMode,
    selectionCount = 0,
}: LongPressHandlerOptions) {
    return (id: string, type: SelectionType): void => {
        const identity: SelectionIdentity = { id, type };
        const wasSelected = isSelected(identity);

        if (!selectMode) {
            toggleSelectMode();
        }

        toggleSelection(identity);

        if (isTouchDevice && wasSelected && selectionCount <= 1) {
            toggleSelectMode();
        }
    };
}

/**
 * Check if device is mobile based on window size and touch capabilities
 */
export function isMobileDevice(): boolean {
    if (typeof window === 'undefined') return false;

    return (
        window.innerWidth < 768 ||
        (navigator && 'maxTouchPoints' in navigator && navigator.maxTouchPoints > 0)
    );
}
