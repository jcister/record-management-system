import type { ComputedRef, Ref } from 'vue';
import type { SelectionIdentity, SelectionType } from '@/utils/selection';
import { createItemClickHandler, createLongPressHandler } from '@/utils/itemClickHandlers';

export type SelectState = 'checked' | 'indeterminate' | 'unchecked';

interface SelectionHelpersOptions {
    isIdentitySelected: (identity: SelectionIdentity) => boolean;
    selectOnly: (identity: SelectionIdentity) => void;
    selectedItems: Ref<SelectionIdentity[]>;
    selectMode: Ref<boolean>;
    lastSelected: Ref<SelectionIdentity | null>;
    selectionCount: ComputedRef<number>;
    toggleSelectionIdentity: (identity: SelectionIdentity) => void;
    toggleSelectMode: () => void;
    isTouchDevice: Ref<boolean>;
    openFolder?: (id: string) => void;
    openFile?: (id: string) => void;
}

export function useSelectionHelpers({
    isIdentitySelected,
    selectOnly,
    selectedItems,
    selectMode,
    lastSelected,
    selectionCount,
    toggleSelectionIdentity,
    toggleSelectMode,
    isTouchDevice,
    openFolder,
    openFile,
}: SelectionHelpersOptions) {
    const isSelected = (id: string, type: SelectionType): boolean =>
        isIdentitySelected({ id, type });

    const selectExclusively = (id: string, type: SelectionType): void => {
        if (isIdentitySelected({ id, type })) {
            return;
        }

        selectOnly({ id, type });
    };

    const handleFolderClick = (id: string, event?: Event): void => {
        if (!openFolder) {
            return;
        }

        const handler = createItemClickHandler({
            selectMode: selectMode.value,
            isTouchDevice: isTouchDevice.value,
            isSelected: isIdentitySelected,
            toggleSelection: toggleSelectionIdentity,
            toggleSelectMode,
            onOpen: openFolder,
            selectionCount: selectionCount.value,
        });

        handler(id, 'folder', event);
    };

    const handleFileClick = (id: string, event?: Event): void => {
        if (!openFile) {
            return;
        }

        const handler = createItemClickHandler({
            selectMode: selectMode.value,
            isTouchDevice: isTouchDevice.value,
            isSelected: isIdentitySelected,
            toggleSelection: toggleSelectionIdentity,
            toggleSelectMode,
            onOpen: openFile,
            selectionCount: selectionCount.value,
        });

        handler(id, 'file', event);
    };

    const handleLongPress = (id: string, type: SelectionType): void => {
        const handler = createLongPressHandler({
            selectMode: selectMode.value,
            isTouchDevice: isTouchDevice.value,
            isSelected: isIdentitySelected,
            toggleSelection: toggleSelectionIdentity,
            toggleSelectMode,
            selectionCount: selectionCount.value,
        });

        handler(id, type);
    };

    const handleFolderContextMenu = (id: string): void => {
        selectExclusively(id, 'folder');
    };

    const handleFileContextMenu = (id: string): void => {
        selectExclusively(id, 'file');
    };

    const resolveSelectState = (items: SelectionIdentity[]): SelectState => {
        if (items.length === 0) {
            return 'unchecked';
        }

        const total = items.length;
        const selectedCount = items.filter(item => isIdentitySelected(item)).length;

        if (selectedCount === 0) {
            return 'unchecked';
        }

        if (selectedCount === total) {
            return 'checked';
        }

        return 'indeterminate';
    };

    const applySectionSelection = (type: SelectionType, identities: SelectionIdentity[]): void => {
        const preserved = selectedItems.value.filter(item => item.type !== type);
        const combined = [...preserved, ...identities];

        selectedItems.value = combined;
        selectMode.value = combined.length > 0;
        lastSelected.value = combined[combined.length - 1] ?? null;
    };

    return {
        isSelected,
        selectExclusively,
        handleFolderClick,
        handleFileClick,
        handleLongPress,
        handleFolderContextMenu,
        handleFileContextMenu,
        resolveSelectState,
        applySectionSelection,
    };
}
