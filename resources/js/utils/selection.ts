import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

export type SelectionType = 'folder' | 'file';

export interface SelectionIdentity {
    id: string;
    type: SelectionType;
}

export const useSelectionState = (
    getOrderedItems?: () => SelectionIdentity[],
) => {
    const selectMode = ref(false);
    const selectedItems = ref<SelectionIdentity[]>([]);
    const shiftPressed = ref(false);
    const lastSelected = ref<SelectionIdentity | null>(null);

    const selectAll = (): void => {
        const ordered = getOrderedItems?.() ?? [];
        if (ordered.length === 0) return;

        selectedItems.value = [...ordered];
        lastSelected.value = ordered[ordered.length - 1] ?? null;
    };

    const handleKeyDown = (event: KeyboardEvent): void => {
        if (event.key === 'Shift') {
            shiftPressed.value = true;
        }

        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'a') {
            if (selectedItems.value.length === 0) {
                return;
            }

            event.preventDefault();
            selectAll();
        }
    };

    const handleKeyUp = (event: KeyboardEvent): void => {
        if (event.key === 'Shift') {
            shiftPressed.value = false;
        }
    };

    onMounted(() => {
        window.addEventListener('keydown', handleKeyDown);
        window.addEventListener('keyup', handleKeyUp);
    });

    onBeforeUnmount(() => {
        window.removeEventListener('keydown', handleKeyDown);
        window.removeEventListener('keyup', handleKeyUp);
    });

    const selectionCount = computed(() => selectedItems.value.length);

    const toggleSelectMode = (): void => {
        selectMode.value = !selectMode.value;
        selectedItems.value = [];
    };

    const toggleSelection = (identity: SelectionIdentity): void => {
        const ordered = getOrderedItems?.() ?? [];

        // Range select when holding Shift and both anchor and target exist in the ordered list
        if (shiftPressed.value && lastSelected.value && ordered.length > 0) {
            const anchorIndex = ordered.findIndex(item => item.id === lastSelected.value?.id && item.type === lastSelected.value?.type);
            const targetIndex = ordered.findIndex(item => item.id === identity.id && item.type === identity.type);

            if (anchorIndex !== -1 && targetIndex !== -1) {
                const [start, end] = anchorIndex < targetIndex
                    ? [anchorIndex, targetIndex]
                    : [targetIndex, anchorIndex];

                selectedItems.value = ordered.slice(start, end + 1);
                lastSelected.value = identity;
                selectMode.value = selectedItems.value.length > 0;
                return;
            }
        }

        const alreadySelected = isSelected(identity);

        // Toggle-on (multi-select) path
        if (selectMode.value || shiftPressed.value) {
            const idx = selectedItems.value.findIndex(item => item.id === identity.id && item.type === identity.type);
            if (idx >= 0) {
                selectedItems.value.splice(idx, 1);
            } else {
                selectedItems.value.push(identity);
            }
            lastSelected.value = isSelected(identity) ? identity : lastSelected.value;
            selectMode.value = selectedItems.value.length > 0;
            return;
        }

        // Single selection path with ability to deselect
        if (alreadySelected) {
            selectedItems.value = [];
            lastSelected.value = null;
            selectMode.value = false;
            return;
        }

        // Single selection path
        selectedItems.value = [identity];
        lastSelected.value = identity;
        // Stay in single-select mode; multi-select should be opt-in via selectMode toggle or Shift
        selectMode.value = false;
    };

    const selectOnly = (identity: SelectionIdentity): void => {
        selectedItems.value = [identity];
        lastSelected.value = identity;
    };

    const isSelected = (identity: SelectionIdentity): boolean => {
        return selectedItems.value.some(item => item.id === identity.id && item.type === identity.type);
    };

    const clearSelection = (): void => {
        if (selectedItems.value.length === 0) {
            return;
        }

        selectedItems.value = [];
        selectMode.value = false;
    };

    return {
        selectMode,
        selectedItems,
        selectionCount,
        toggleSelectMode,
        toggleSelection,
        selectOnly,
        isSelected,
        clearSelection,
        lastSelected,
    } as const;
};
