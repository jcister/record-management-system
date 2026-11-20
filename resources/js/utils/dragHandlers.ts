import { apiClient } from '@/lib/axios';
import type { ConfirmOptions } from '@/composables/useConfirm';
import type { Ref } from 'vue';

export type DragTargetType = 'folder' | 'file';

export interface DraggedItem {
    id: string;
    type: DragTargetType;
    name: string;
}

interface DragHandlerOptions {
    draggedItem: Ref<DraggedItem | null>;
    dropTargetId?: Ref<string | null>;
    draggedItems?: Ref<DraggedItem[]>;
}

type ConfirmResult = 'confirm' | 'replace' | 'cancel' | string | undefined;

interface RouterLike {
    reload: (options?: { only?: string[] }) => Promise<void> | void;
}

interface FolderSummary {
    id: string;
    name: string;
}

interface DropHandlerOptions {
    draggedItem: Ref<DraggedItem | null>;
    draggedItems?: Ref<DraggedItem[]>;
    dropTargetId: Ref<string | null>;
    foldersSource: () => FolderSummary[];
    router: RouterLike;
    showConfirm: (options: ConfirmOptions) => Promise<ConfirmResult>;
    success: (title: string, description?: string) => void;
    error: (title: string, description?: string) => void;
    reloadOnly?: string[];
}

export const createDragHandlers = ({ draggedItem, dropTargetId, draggedItems }: DragHandlerOptions) => {
    const handleDragStart = (item: DraggedItem, event: DragEvent): void => {
        draggedItem.value = item;

        if (event.dataTransfer) {
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', JSON.stringify(item));
        }
    };

    const handleDragEnd = (): void => {
        draggedItem.value = null;
        if (dropTargetId) {
            dropTargetId.value = null;
        }
        if (draggedItems) {
            draggedItems.value = [];
        }
    };

    const handleDragOver = (event: DragEvent, targetId: string): void => {
        event.preventDefault();

        if (!draggedItem.value || draggedItem.value.id === targetId) {
            return;
        }

        if (dropTargetId) {
            dropTargetId.value = targetId;
        }

        if (event.dataTransfer) {
            event.dataTransfer.dropEffect = 'move';
        }
    };

    const handleDragLeave = (): void => {
        if (dropTargetId) {
            dropTargetId.value = null;
        }
    };

    return {
        handleDragStart,
        handleDragEnd,
        handleDragOver,
        handleDragLeave,
    } as const;
};

type MoveResult = 'moved' | 'cancelled';

const normalizeCollection = (payload: any): any[] => {
    if (Array.isArray(payload?.data)) {
        return [...payload.data];
    }

    if (Array.isArray(payload)) {
        return [...payload];
    }

    return [];
};

export const createDropHandler = ({
    draggedItem,
    draggedItems,
    dropTargetId,
    foldersSource,
    router,
    showConfirm,
    success,
    error,
    reloadOnly = ['folders'],
}: DropHandlerOptions) => {
    const resetDragState = (): void => {
        draggedItem.value = null;
        if (draggedItems) {
            draggedItems.value = [];
        }
    };

    const resolveItemsToMove = (): DraggedItem[] => {
        if (draggedItems?.value?.length) {
            return draggedItems.value.map(item => ({ ...item }));
        }

        return draggedItem.value ? [{ ...draggedItem.value }] : [];
    };

    const ensureFileEntry = (existingFiles: any[], id: string, name: string): void => {
        const index = existingFiles.findIndex((file: any) => file.id === id);
        if (index >= 0) {
            existingFiles[index].file_name = name;
            return;
        }

        existingFiles.push({ id, file_name: name });
    };

    const buildNextFileName = (currentName: string, existingFiles: any[]): string => {
        const nameParts = currentName.match(/^(.+?)(\.[^.]*)?$/);
        const baseName = nameParts ? nameParts[1] : currentName;
        const extension = nameParts && nameParts[2] ? nameParts[2] : '';

        let counter = 1;
        let newFileName = `${baseName} (${counter})${extension}`;

        while (existingFiles.some((file: any) => file.file_name === newFileName)) {
            counter++;
            newFileName = `${baseName} (${counter})${extension}`;
        }

        return newFileName;
    };

    const handleDuplicateFromApi = async (
        err: any,
        item: DraggedItem,
        targetFolderId: string,
        folderName: string,
        existingFiles: any[],
    ): Promise<MoveResult> => {
        const errorCode = err?.response?.data?.code;
        if (errorCode !== 'duplicate_filename') {
            throw err;
        }

        const suggestedName = err?.response?.data?.suggested_name as string | undefined;
        const duplicateId = err?.response?.data?.duplicate_id as string | undefined;

        const choice = await showConfirm({
            title: 'Duplicate File Name',
            description: `A file named "${item.name}" already exists in ${folderName}. What would you like to do?`,
            replaceText: 'Replace existing',
            confirmText: 'Rename automatically',
            cancelText: 'Cancel',
        });

        if (choice === 'replace' && duplicateId) {
            await apiClient.patch(`/api/files/${item.id}/move`, {
                folder_id: targetFolderId,
                strategy: 'replace',
            });

            success('Replaced successfully', `${item.name} has replaced the existing file in ${folderName}.`);

            const duplicateIndex = existingFiles.findIndex((file: any) => file.id === duplicateId);
            if (duplicateIndex !== -1) {
                existingFiles.splice(duplicateIndex, 1);
            }

            ensureFileEntry(existingFiles, item.id, item.name);
            return 'moved';
        }

        if (choice === 'confirm') {
            const newFileName = suggestedName ?? buildNextFileName(item.name, existingFiles);

            await apiClient.patch(`/api/files/${item.id}/move`, {
                folder_id: targetFolderId,
                strategy: 'rename',
                new_name: newFileName,
            });

            success('Moved successfully', `${item.name} has been moved to ${folderName} and renamed to ${newFileName}.`);

            ensureFileEntry(existingFiles, item.id, newFileName);
            item.name = newFileName;
            return 'moved';
        }

        return 'cancelled';
    };

    const ensureFolderEntry = (existingFolders: any[], id: string, name: string): void => {
        const index = existingFolders.findIndex((folder: any) => folder.id === id);
        if (index >= 0) {
            existingFolders[index].name = name;
            return;
        }

        existingFolders.push({ id, name });
    };

    const handleSingleFileMove = async (
        item: DraggedItem,
        targetFolderId: string,
        folderName: string,
        existingFiles: any[],
    ): Promise<MoveResult> => {
        const duplicateFile = existingFiles.find((file: any) => file.file_name === item.name && file.id !== item.id);

        if (duplicateFile) {
            const userChoice = await showConfirm({
                title: 'Duplicate File Name',
                description: `A file named "${item.name}" already exists in ${folderName}. What would you like to do?`,
                replaceText: 'Replace existing',
                confirmText: 'Rename automatically',
                cancelText: 'Cancel',
            });

            if (userChoice !== 'confirm' && userChoice !== 'replace') {
                return 'cancelled';
            }

            if (userChoice === 'replace') {
                await apiClient.delete(`/api/files/${duplicateFile.id}`);
                await apiClient.patch(`/api/files/${item.id}/move`, {
                    folder_id: targetFolderId,
                });

                success('Replaced successfully', `${item.name} has replaced the existing file in ${folderName}.`);

                const duplicateIndex = existingFiles.findIndex((file: any) => file.id === duplicateFile.id);
                if (duplicateIndex !== -1) {
                    existingFiles.splice(duplicateIndex, 1);
                }

                ensureFileEntry(existingFiles, item.id, item.name);
                return 'moved';
            }

            const newFileName = buildNextFileName(item.name, existingFiles);

            await apiClient.patch(`/api/files/${item.id}/move`, {
                folder_id: targetFolderId,
                new_name: newFileName,
            });

            success('Moved successfully', `${item.name} has been moved to ${folderName} and renamed to ${newFileName}.`);

            ensureFileEntry(existingFiles, item.id, newFileName);
            item.name = newFileName;
            return 'moved';
        }

        try {
            await apiClient.patch(`/api/files/${item.id}/move`, {
                folder_id: targetFolderId,
            });

            success('Moved successfully', `${item.name} has been moved to ${folderName}.`);
            ensureFileEntry(existingFiles, item.id, item.name);

            return 'moved';
        } catch (err: any) {
            return handleDuplicateFromApi(err, item, targetFolderId, folderName, existingFiles);
        }
    };

    const handleSingleFolderMove = async (
        item: DraggedItem,
        targetFolderId: string,
        folderName: string,
        existingFolders: any[],
    ): Promise<MoveResult> => {
        const duplicateFolder = existingFolders.find((folder: any) =>
            folder.name === item.name && folder.id !== item.id
        );

        if (duplicateFolder) {
            const userChoice = await showConfirm({
                title: 'Duplicate Folder Name',
                description: `A folder named "${item.name}" already exists in ${folderName}. What would you like to do?`,
                confirmText: 'Rename automatically',
                cancelText: 'Cancel',
            });

            if (userChoice !== 'confirm') {
                return 'cancelled';
            }

            let counter = 1;
            let newFolderName = `${item.name} (${counter})`;

            while (existingFolders.some((folder: any) => folder.name === newFolderName)) {
                counter++;
                newFolderName = `${item.name} (${counter})`;
            }

            await apiClient.patch(`/api/folders/${item.id}/move`, {
                parent_id: targetFolderId,
                new_name: newFolderName,
            });

            success('Moved successfully', `${item.name} has been moved to ${folderName} and renamed to ${newFolderName}.`);

            ensureFolderEntry(existingFolders, item.id, newFolderName);
            item.name = newFolderName;
            return 'moved';
        }

        await apiClient.patch(`/api/folders/${item.id}/move`, {
            parent_id: targetFolderId,
        });

        success('Moved successfully', `${item.name} has been moved to ${folderName}.`);
        ensureFolderEntry(existingFolders, item.id, item.name);

        return 'moved';
    };

    return async (event: DragEvent, targetFolderId: string): Promise<void> => {
        event.preventDefault();
        event.stopPropagation();

        dropTargetId.value = null;

        const pendingItems = resolveItemsToMove();

        if (pendingItems.length === 0) {
            return;
        }

        const itemsToMove = pendingItems.filter(item => item.type !== 'folder' || item.id !== targetFolderId);

        if (itemsToMove.length === 0) {
            resetDragState();
            return;
        }

        try {
            const targetFolder = foldersSource().find(folder => folder.id === targetFolderId);
            const folderName = targetFolder?.name || 'the selected folder';

            const filesToMove = itemsToMove.filter(item => item.type === 'file');
            const foldersToMove = itemsToMove.filter(item => item.type === 'folder');

            let movedAny = false;

            if (filesToMove.length > 0) {
                const response = await apiClient.get(`/api/files?folder_id=${targetFolderId}`);
                const existingFiles = normalizeCollection(response.data);

                for (const fileItem of filesToMove) {
                    const result = await handleSingleFileMove(fileItem, targetFolderId, folderName, existingFiles);

                    if (result === 'cancelled') {
                        resetDragState();
                        return;
                    }

                    movedAny = movedAny || result === 'moved';
                }
            }

            if (foldersToMove.length > 0) {
                const response = await apiClient.get(`/api/folders?parent_id=${targetFolderId}&per_page=all`);
                const existingFolders = normalizeCollection(response.data);

                for (const folderItem of foldersToMove) {
                    const result = await handleSingleFolderMove(folderItem, targetFolderId, folderName, existingFolders);

                    if (result === 'cancelled') {
                        resetDragState();
                        return;
                    }

                    movedAny = movedAny || result === 'moved';
                }
            }

            if (movedAny) {
                router.reload({ only: reloadOnly });
            }

            resetDragState();
        } catch (err: any) {
            const errorMessage = err.response?.data?.message || err.message || 'Please try again.';
            error('Failed to move', errorMessage);
            resetDragState();
        }
    };
};
