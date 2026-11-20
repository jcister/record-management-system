import { router } from '@inertiajs/vue3';
import { apiClient } from '@/lib/axios';
import { destroy as destroyFile } from '@/routes/api/files';
import { destroy as destroyFolder } from '@/routes/api/folders';
import { download as downloadRoute } from '@/routes/files';
import type { SelectedItemType } from '@/types';

export interface ActionCallbacks {
    error: (title: string, message?: string) => void;
    success: (title: string, message?: string) => void;
    confirm: (options: {
        title: string;
        description: string;
        confirmText: string;
        cancelText: string;
        replaceText?: string;
        variant?: 'default' | 'destructive';
    }) => Promise<'confirm' | 'replace' | 'cancel'>;
}

/**
 * Deduplicate items by type and id
 */
export function deduplicateItems<T extends { id: string; type: string }>(items: T[]): T[] {
    const seen = new Set<string>();
    return items.filter(item => {
        const key = `${item.type}-${item.id}`;
        if (seen.has(key)) return false;
        seen.add(key);
        return true;
    });
}

/**
 * Pluralize a word based on count
 */
export function pluralize(count: number, word: string): string {
    return count === 1 ? word : `${word}s`;
}

/**
 * Extract filename from Content-Disposition header
 */
export function extractFilename(disposition?: string): string | null {
    if (!disposition) return null;
    const match = /filename\*=UTF-8''([^;]+)|filename="?([^";]+)"?/i.exec(disposition);
    if (!match) return null;
    return decodeURIComponent(match[1] || match[2] || '').trim();
}

/**
 * Trigger direct file download
 */
export function triggerDirectFileDownload(fileId: string): void {
    const route = downloadRoute(fileId);
    const link = document.createElement('a');
    link.href = route.url;
    link.download = '';
    document.body.appendChild(link);
    link.click();
    link.remove();
}

/**
 * Download single file
 */
export async function downloadFile(
    fileId: string,
    callbacks: Pick<ActionCallbacks, 'error'>
): Promise<void> {
    try {
        triggerDirectFileDownload(fileId);
    } catch (err: any) {
        const message = err?.response?.data?.message || err?.message || 'Please try again.';
        callbacks.error('Failed to download file', message);
    }
}

/**
 * Download folder or multiple items as zip
 */
export async function downloadAsZip(
    items: Array<{ id: string; type: 'folder' | 'file' }>,
    callbacks: Pick<ActionCallbacks, 'error'>
): Promise<void> {
    try {
        const response = await apiClient.post(
            '/api/downloads',
            { items },
            { responseType: 'blob' }
        );

        const blob = new Blob([response.data], { type: 'application/zip' });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download =
            extractFilename(response.headers['content-disposition']) || 'ched-onse-kmis-download.zip';
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    } catch (err: any) {
        const message = err?.response?.data?.message || err?.message || 'Please try again.';
        callbacks.error('Failed to download', message);
    }
}

/**
 * Handle download for single or multiple items
 */
export async function handleDownload(
    items: SelectedItemType[],
    callbacks: Pick<ActionCallbacks, 'error'>
): Promise<void> {
    const dedupedItems = deduplicateItems(items);
    const downloadable = dedupedItems.filter(item => item.type === 'folder' || item.hasFile !== false);

    if (downloadable.length === 0) {
        callbacks.error('No files to download', 'Attach a file to the selected record(s) first.');
        return;
    }

    if (downloadable.length !== dedupedItems.length) {
        callbacks.error('Some items skipped', 'Items without files were skipped. Attach files to download them.');
    }

    if (downloadable.length === 1 && downloadable[0].type === 'file') {
        await downloadFile(downloadable[0].id, callbacks);
        return;
    }

    await downloadAsZip(downloadable, callbacks);
}

/**
 * Delete single item
 */
export async function deleteItem(
    itemId: string,
    itemType: 'folder' | 'file',
    itemName: string,
    callbacks: ActionCallbacks
): Promise<boolean> {
    const confirmed = await callbacks.confirm({
        title: `Move to trash`,
        description: `Are you sure you want to move "${itemName}" to trash? This action cannot be undone.`,
        confirmText: 'Yes',
        cancelText: 'No',
        variant: 'destructive',
    });

    if (confirmed !== 'confirm') {
        return false;
    }

    try {
        const destroyRoute = itemType === 'folder' ? destroyFolder : destroyFile;
        await apiClient.delete(destroyRoute(itemId).url);
        router.reload({ only: [itemType === 'folder' ? 'folders' : 'files'] });
        callbacks.success(`${itemType === 'folder' ? 'Folder' : 'File'} moved to trash successfully`);
        return true;
    } catch {
        callbacks.error(`Failed to move ${itemType} to trash`, 'Please try again.');
        return false;
    }
}

/**
 * Delete multiple items
 */
export async function deleteMultipleItems(
    items: SelectedItemType[],
    callbacks: ActionCallbacks,
    selectionCount?: number,
    options?: { permanent?: boolean }
): Promise<boolean> {
    if (items.length === 0) return false;

    const dedupedItems = deduplicateItems(items);

    const permanent = options?.permanent ?? false;
    const count = selectionCount || dedupedItems.length;
    const confirmed = await callbacks.confirm({
        title: permanent
            ? `Delete ${pluralize(count, 'item')} permanently`
            : `Move ${pluralize(count, 'item')} to trash`,
        description: permanent
            ? `Are you sure you want to permanently delete ${count} ${pluralize(count, 'item')}? This cannot be undone.`
            : `Are you sure you want to move ${count} ${pluralize(count, 'item')} to trash?`,
        confirmText: permanent ? 'Delete' : 'Yes',
        cancelText: 'No',
        variant: 'destructive',
    });

    if (confirmed !== 'confirm') {
        return false;
    }

    let failures = 0;
    let successes = 0;

    for (const item of dedupedItems) {
        try {
            if (permanent) {
                const forcePath = item.type === 'folder' ? `/api/folders/${item.id}/force` : `/api/files/${item.id}/force`;
                await apiClient.delete(forcePath);
            } else {
                const destroyRoute = item.type === 'folder' ? destroyFolder : destroyFile;
                await apiClient.delete(destroyRoute(item.id).url);
            }
            successes += 1;
        } catch {
            failures += 1;
        }
    }

    if (successes > 0) {
        router.reload({ only: ['folders', 'files', 'bookmarks'] });
        callbacks.success(
            permanent ? 'Deleted permanently' : 'Deleted successfully',
            `${successes} ${pluralize(successes, 'item')} ${permanent ? 'permanently deleted' : 'deleted'}.`
        );
    }

    if (failures > 0) {
        callbacks.error(
            permanent ? 'Failed to delete some items permanently' : 'Failed to delete some items',
            `${failures} ${pluralize(failures, 'item')} could not be deleted. Please try again.`
        );
    }

    return successes > 0;
}

/**
 * Restore multiple trashed items
 */
export async function restoreMultipleItems(
    items: SelectedItemType[],
    callbacks: ActionCallbacks,
    selectionCount?: number
): Promise<boolean> {
    if (items.length === 0) return false;

    const count = selectionCount || items.length;
    const confirmed = await callbacks.confirm({
        title: `Restore ${pluralize(count, 'item')}`,
        description: `Are you sure you want to restore ${count} ${pluralize(count, 'item')}?`,
        confirmText: 'Restore',
        cancelText: 'Cancel',
    });

    if (confirmed !== 'confirm') {
        return false;
    }

    const isSingleFileRestore = items.length === 1 && items[0].type === 'file';

    if (isSingleFileRestore) {
        const item = items[0];

        try {
            await apiClient.post(`/api/files/${item.id}/restore`);
            router.reload({ only: ['files', 'bookmarks'] });
            callbacks.success('File restored successfully');

            return true;
        } catch (err: any) {
            const responseData = err?.response?.data;
            const duplicateFileName =
                responseData?.code === 'duplicate_filename' ||
                responseData?.code === 'duplicate_file_name';

            if (duplicateFileName) {
                const originalName = item.name;
                const suggestedName = responseData?.suggested_name as string | undefined;

                const choice = await callbacks.confirm({
                    title: 'Duplicate File Name',
                    description: `A file named "${originalName}" already exists in this folder. What would you like to do?`,
                    replaceText: 'Replace existing',
                    confirmText: 'Rename automatically',
                    cancelText: 'Cancel',
                });

                if (choice === 'replace') {
                    await apiClient.post(`/api/files/${item.id}/restore`, {
                        strategy: 'replace',
                    });
                    router.reload({ only: ['files', 'bookmarks'] });
                    callbacks.success('File restored successfully', `${originalName} replaced the existing file.`);

                    return true;
                }

                if (choice === 'confirm') {
                    await apiClient.post(`/api/files/${item.id}/restore`, {
                        strategy: 'rename',
                        new_name: suggestedName ?? originalName,
                    });
                    router.reload({ only: ['files', 'bookmarks'] });
                    callbacks.success('File restored successfully', `${originalName} restored with a new name.`);

                    return true;
                }

                return false;
            }

            const message = responseData?.message || 'Please try again.';
            callbacks.error('Failed to restore file', message);

            return false;
        }
    }

    try {
        for (const item of items) {
            const endpoint = item.type === 'folder'
                ? `/api/folders/${item.id}/restore`
                : `/api/files/${item.id}/restore`;

            await apiClient.post(endpoint);
        }

        router.reload({ only: ['folders', 'files', 'bookmarks'] });
        callbacks.success('Restored successfully', `${count} ${pluralize(count, 'item')} restored.`);
        return true;
    } catch {
        callbacks.error('Failed to restore selection', 'Please try again.');
        return false;
    }
}

/**
 * Add bookmark for single item
 */
export async function addBookmark(
    itemId: string,
    itemType: 'folder' | 'file',
    itemName: string,
    callbacks: Pick<ActionCallbacks, 'error' | 'success'>
): Promise<boolean> {
    try {
        await apiClient
            .post('/api/bookmarks', {
                bookmarkable_id: itemId,
                bookmarkable_type:
                    itemType === 'folder' ? 'App\\Models\\Folder' : 'App\\Models\\File',
            })
            .catch(err => {
                if (
                    err?.response?.status === 409 ||
                    (err?.response?.data?.message || '').includes('UNIQUE constraint')
                ) {
                    return;
                }
                throw err;
            });

        router.reload({ only: ['folders', 'files'] });
        callbacks.success('Bookmarked!', `${itemName} has been added to your bookmarks.`);
        return true;
    } catch (err: any) {
        const message = err?.response?.data?.message || err?.message || 'Please try again.';
        callbacks.error('Failed to bookmark', message);
        return false;
    }
}

/**
 * Remove bookmark for single item
 */
export async function removeBookmark(
    bookmarkId: string,
    itemName: string,
    callbacks: ActionCallbacks
): Promise<boolean> {
    const confirmed = await callbacks.confirm({
        title: 'Remove Bookmark',
        description: `Are you sure you want to remove "${itemName}" from bookmarks?`,
        confirmText: 'Remove',
        cancelText: 'Cancel',
    });

    if (confirmed !== 'confirm') {
        return false;
    }

    try {
        await apiClient.delete(`/api/bookmarks/${bookmarkId}`);
        callbacks.success('Unbookmarked!', `${itemName} has been removed from your bookmarks.`);
        return true;
    } catch (err: any) {
        const message = err?.response?.data?.message || err?.message || 'Please try again.';
        callbacks.error('Failed to unbookmark', message);
        return false;
    }
}

/**
 * Toggle bookmark for single item
 */
export async function toggleBookmark(
    item: {
        id: string;
        type: 'folder' | 'file';
        name: string;
        isBookmarked?: boolean;
        bookmarkId?: string;
    },
    callbacks: ActionCallbacks
): Promise<boolean> {
    if (item.isBookmarked) {
        if (!item.bookmarkId) {
            callbacks.error('Cannot unbookmark', 'Bookmark ID not found.');
            return false;
        }
        return await removeBookmark(item.bookmarkId, item.name, callbacks);
    }

    return await addBookmark(item.id, item.type, item.name, callbacks);
}

/**
 * Add bookmarks for multiple items
 */
export async function addMultipleBookmarks(
    items: SelectedItemType[],
    callbacks: Pick<ActionCallbacks, 'error' | 'success'>
): Promise<{ created: number; duplicates: number }> {
    const dedupedItems = deduplicateItems(items);
    let created = 0;
    let duplicates = 0;

    await Promise.all(
        dedupedItems.map(item =>
            apiClient
                .post('/api/bookmarks', {
                    bookmarkable_id: item.id,
                    bookmarkable_type:
                        item.type === 'folder' ? 'App\\Models\\Folder' : 'App\\Models\\File',
                })
                .then(() => {
                    created += 1;
                })
                .catch(err => {
                    if (
                        err?.response?.status === 409 ||
                        (err?.response?.data?.message || '').includes('UNIQUE constraint')
                    ) {
                        duplicates += 1;
                        return;
                    }
                    throw err;
                })
        )
    );

    const total = created + duplicates;
    if (total > 0) {
        callbacks.success('Bookmarked!', `${total} ${pluralize(total, 'item')} added to bookmarks.`);
    } else {
        callbacks.error('Failed to bookmark selection', 'Please try again.');
    }

    return { created, duplicates };
}

/**
 * Remove bookmarks for multiple items
 */
export async function removeMultipleBookmarks(
    items: SelectedItemType[],
    callbacks: ActionCallbacks
): Promise<boolean> {
    const targets = items.filter(item => item.bookmarkId);

    if (targets.length === 0) {
        callbacks.error('Cannot unbookmark', 'Bookmark references missing for selected items.');
        return false;
    }

    const confirmed = await callbacks.confirm({
        title: 'Remove Bookmarks',
        description: `Are you sure you want to remove ${targets.length} ${pluralize(targets.length, 'item')} from bookmarks?`,
        confirmText: 'Remove',
        cancelText: 'Cancel',
    });

    if (confirmed !== 'confirm') {
        return false;
    }

    try {
        await Promise.all(targets.map(item => apiClient.delete(`/api/bookmarks/${item.bookmarkId}`)));
        callbacks.success(
            'Unbookmarked',
            `${targets.length} ${pluralize(targets.length, 'item')} removed from bookmarks.`
        );
        return true;
    } catch (err: any) {
        const message = err?.response?.data?.message || err?.message || 'Please try again.';
        callbacks.error('Failed to unbookmark selection', message);
        return false;
    }
}

/**
 * Toggle bookmarks for multiple items
 */
export async function toggleMultipleBookmarks(
    items: SelectedItemType[],
    callbacks: ActionCallbacks
): Promise<boolean> {
    if (items.length === 0) {
        callbacks.error('Nothing selected', 'Select an item first.');
        return false;
    }

    const dedupedItems = deduplicateItems(items);
    const unbookmarked = dedupedItems.filter(item => !item.isBookmarked);
    const allBookmarked = unbookmarked.length === 0;

    let success: boolean;

    if (allBookmarked) {
        success = await removeMultipleBookmarks(dedupedItems, callbacks);
    } else {
        const result = await addMultipleBookmarks(unbookmarked, callbacks);
        success = result.created > 0 || result.duplicates > 0;
    }

    if (success) {
        router.reload({ only: ['folders', 'files', 'bookmarks'] });
    }

    return success;
}
