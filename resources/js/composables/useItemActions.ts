import axios from '@/lib/axios';
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useConfirm } from '@/composables/useConfirm';
import { useToast } from '@/composables/useToast';
import { usePermissions } from '@/utils/permission';
import {
    handleDownload as performDownload,
    deleteItem,
    toggleBookmark as performToggleBookmark,
    type ActionCallbacks,
} from '@/utils/actions';
import { formatFileSize } from '@/utils/format';
import type { Department, File as FileSchema, FileVersion, TagSchema } from '@/types/schema';

export type ItemActionType = 'folder' | 'file';

export type FileMetadata = Pick<
    FileSchema,
    'file_name' | 'file_size' | 'record_id' | 'author' | 'department_id' | 'physical_location' | 'folder_id' | 'is_record' | 'date_created' | 'date_released' | 'retention_duration' | 'retention_expires_at' | 'retention_status' | 'tags' | 'created_at' | 'updated_at'
> & {
    name?: string;
    folder?: { id: string; name: string } | null;
    current_version_id?: string | null;
    current_version?: FileVersion | null;
    disposal_date?: string | null;
};

export interface ItemActionProps {
    itemId: string;
    itemType: ItemActionType;
    itemName: string;
    isBookmarked?: boolean;
    bookmarkId?: string;
    currentFolderId?: string | null;
    currentFolderName?: string | null;
    inline?: boolean;
    hasFile?: boolean;
    metadata?: FileMetadata;
}

export interface ItemActionEmitters {
    unbookmarked?: (itemId: string, itemType: ItemActionType) => void;
    renamed?: (itemId: string, itemType: ItemActionType, newName: string) => void;
    attachFile?: (itemId: string, itemType: ItemActionType) => void;
    showVersions?: (itemId: string) => void;
}

export const useItemActions = (
    props: ItemActionProps,
    emit?: ItemActionEmitters,
) => {
    const { confirm: showConfirm } = useConfirm();
    const { error, success } = useToast();
    const { can } = usePermissions();

    const showMoveDialog = ref(false);
    const showRenameDialog = ref(false);
    const showInfoSheet = ref(false);

    watch(showInfoSheet, (isOpen) => {
        if (isOpen) {
            setTimeout(() => {
                const sheet = document.querySelector('[data-slot="sheet-content"]');
                if (sheet) {
                    (sheet as HTMLElement).focus();
                }
            }, 0);
        }
    });

    const callbacks: ActionCallbacks = {
        error,
        success,
        confirm: showConfirm,
    };

    const page = usePage();
    const departments = computed<Department[]>(() => (page.props.departments as Department[] | undefined) ?? []);
    const isTrashPage = computed(() => page.url?.startsWith('/records/trash'));
    const isBookmarksPage = computed(() => page.url?.startsWith('/records/bookmarks'));

    const departmentName = computed(() => {
        if (!props.metadata?.department_id) {
            return undefined;
        }

        const match = departments.value.find((dept) => String(dept.id) === String(props.metadata?.department_id));

        return match?.name ?? undefined;
    });

    const normalizeValue = (value: unknown): string => {
        if (value === undefined || value === null || value === '') {
            return '—';
        }

        return String(value);
    };

    const fileSizeLabel = computed(() => {
        if (!props.metadata) {
            return '—';
        }

        if (props.itemType === 'file' && props.hasFile === false) {
            return 'No file attached';
        }

        if (typeof props.metadata.file_size === 'number' && props.metadata.file_size > 0) {
            return formatFileSize(props.metadata.file_size);
        }

        return '—';
    });

    const folderName = computed(() => props.currentFolderName ?? props.metadata?.folder?.name ?? props.metadata?.folder_id ?? props.currentFolderId ?? '—');

    const formatDate = (dateString: string | null | undefined): string => {
        if (!dateString) {
            return '—';
        }

        const parsed = new Date(dateString);
        if (Number.isNaN(parsed.getTime())) {
            return '—';
        }

        return parsed.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    };

    const formatRetentionDuration = (duration: string | null | undefined): string => {
        if (!duration) {
            return '—';
        }

        const durationMap: Record<string, string> = {
            one_year: '1 Year',
            ten_years: '10 Years',
            perpetual: 'Perpetual',
        };

        return durationMap[duration] || duration;
    };

    const formatRetentionStatus = (status: string | null | undefined): string => {
        if (!status) {
            return '—';
        }

        const statusMap: Record<string, string> = {
            active: 'Active',
            archived: 'Archived',
            ready_for_disposal: 'Ready for Disposal',
            disposed: 'Disposed',
        };

        return statusMap[status] || status;
    };

    const formatKeywords = (tags: TagSchema[] | undefined): string => {
        if (!tags || tags.length === 0) {
            return '—';
        }

        const metadataTags = tags.filter((tag) => tag.type === 'metadata');

        if (metadataTags.length === 0) {
            return '—';
        }

        return metadataTags.map((tag) => tag.name).join(', ');
    };

    const metadataEntries = computed(() => {
        if (!props.metadata) {
            return [] as { label: string; value: string }[];
        }

        const entries = [
            { label: 'Name', value: props.metadata.name ?? props.itemName },
            // { label: 'File name', value: props.metadata.file_name },
            { label: 'File size', value: fileSizeLabel.value },
            { label: 'Record ID', value: props.metadata.record_id },
            { label: 'Author', value: props.metadata.author },
            { label: 'Division', value: departmentName.value ?? props.metadata.department_id },
            { label: 'Physical location', value: props.metadata.physical_location },
            { label: 'Folder', value: folderName.value },
            { label: 'Date created', value: formatDate(props.metadata.date_created) },
            { label: 'Date released', value: formatDate(props.metadata.date_released) },
            { label: 'Retention', value: formatRetentionDuration(props.metadata.retention_duration) },
            { label: 'Retention status', value: formatRetentionStatus(props.metadata.retention_status) },
        ];

        // Add disposal date if the record is disposed
        if (props.metadata.retention_status === 'disposed' && props.metadata.disposal_date) {
            entries.push({ label: 'Date of disposal', value: formatDate(props.metadata.disposal_date) });
        }

        entries.push({ label: 'Keywords', value: formatKeywords(props.metadata.tags) });

        return entries.map(({ label, value }) => ({
            label,
            value: normalizeValue(value),
        }));
    });

    const hasMetadata = computed(() => metadataEntries.value.length > 0);

    watch(
        () => props.metadata,
        (value) => {
            if (!value) {
                showInfoSheet.value = false;
            }
        },
    );

    const onRenamed = (id: string, type: ItemActionType, newName: string): void => {
        emit?.renamed?.(id, type, newName);
    };

    const handleDownload = async (): Promise<void> => {
        if (props.itemType === 'file' && props.hasFile === false) {
            error('No file attached', 'Attach a file before downloading.');
            return;
        }

        await performDownload(
            [{ id: props.itemId, type: props.itemType, name: props.itemName }],
            callbacks,
        );
    };

    const handleDelete = async (): Promise<void> => {
        await deleteItem(props.itemId, props.itemType, props.itemName, callbacks);
    };

    const handleRestoreTrash = async (): Promise<void> => {
        if (!isTrashPage.value) {
            return;
        }

        const isFolder = props.itemType === 'folder';

        const confirmed = await showConfirm({
            title: isFolder ? 'Restore Folder' : 'Restore File',
            description: isFolder
                ? 'Are you sure you want to restore this folder?'
                : 'Are you sure you want to restore this file?',
            confirmText: 'Restore',
            cancelText: 'Cancel',
        });

        if (confirmed !== 'confirm') {
            return;
        }

        try {
            const endpoint = isFolder
                ? `/api/folders/${props.itemId}/restore`
                : `/api/files/${props.itemId}/restore`;

            await axios.post(endpoint);
            router.reload({ only: [isFolder ? 'folders' : 'files'] });
            success(isFolder ? 'Folder restored successfully' : 'File restored successfully');
        } catch (err: any) {
            const responseData = err?.response?.data;
            const duplicateFileName =
                responseData?.code === 'duplicate_filename' ||
                responseData?.code === 'duplicate_file_name';

            if (!isFolder && duplicateFileName) {
                const currentName = props.metadata?.file_name ?? props.itemName;
                const suggestedName = responseData?.suggested_name as string | undefined;

                const choice = await showConfirm({
                    title: 'Duplicate File Name',
                    description: `A file named "${currentName}" already exists in this folder. What would you like to do?`,
                    replaceText: 'Replace existing',
                    confirmText: 'Rename automatically',
                    cancelText: 'Cancel',
                });

                if (choice === 'replace') {
                    await axios.post(`/api/files/${props.itemId}/restore`, {
                        strategy: 'replace',
                    });
                    router.reload({ only: ['files'] });
                    success('File restored successfully', `${currentName} replaced the existing file.`);
                    return;
                }

                if (choice === 'confirm') {
                    await axios.post(`/api/files/${props.itemId}/restore`, {
                        strategy: 'rename',
                        new_name: suggestedName ?? currentName,
                    });
                    router.reload({ only: ['files'] });
                    success('File restored successfully', `${currentName} restored with a new name.`);
                    return;
                }

                return;
            }

            const message = responseData?.message || 'Please try again.';
            error(isFolder ? 'Failed to restore folder' : 'Failed to restore file', message);
        }
    };

    const handleDeletePermanent = async (): Promise<void> => {
        if (!isTrashPage.value) {
            return;
        }

        const isFolder = props.itemType === 'folder';

        const confirmed = await showConfirm({
            title: isFolder ? 'Delete Folder' : 'Delete File',
            description: isFolder
                ? 'Are you sure you want to delete this folder?'
                : 'Are you sure you want to delete this file?',
            confirmText: 'Delete',
            cancelText: 'Cancel',
            variant: 'destructive',
        });

        if (confirmed !== 'confirm') {
            return;
        }

        try {
            const endpoint = isFolder
                ? `/api/folders/${props.itemId}/force`
                : `/api/files/${props.itemId}/force`;

            await axios.delete(endpoint);
            router.reload({ only: [isFolder ? 'folders' : 'files'] });
            success(isFolder ? 'Folder deleted successfully' : 'File deleted successfully');
        } catch {
            error(isFolder ? 'Failed to delete folder' : 'Failed to delete file', 'Please try again.');
        }
    };

    const handleAttach = (): void => {
        emit?.attachFile?.(props.itemId, props.itemType);
    };

    const handleShowVersions = (): void => {
        if (props.itemType === 'file') {
            emit?.showVersions?.(props.itemId);
        }
    };

    const handleToggleBookmark = async (): Promise<void> => {
        const success = await performToggleBookmark(
            {
                id: props.itemId,
                type: props.itemType,
                name: props.itemName,
                isBookmarked: props.isBookmarked,
                bookmarkId: props.bookmarkId,
            },
            callbacks,
        );

        if (success && props.isBookmarked) {
            emit?.unbookmarked?.(props.itemId, props.itemType);
        }
    };

    const attachLabel = computed(() => {
        if (props.hasFile === false) return 'Attach file';
        if (props.itemType === 'file' && props.metadata?.is_record === false) return 'Update file';
        return 'Update record';
    });

    return {
        showMoveDialog,
        showRenameDialog,
        showInfoSheet,
        hasMetadata,
        metadataEntries,
        attachLabel,
        departmentName,
        fileSizeLabel,
        can,
        handleAttach,
        handleDelete,
        handleDeletePermanent,
        handleDownload,
        handleShowVersions,
        handleToggleBookmark,
        handleRestoreTrash,
        onRenamed,
        isTrashPage,
        isBookmarksPage,
    } as const;
};
