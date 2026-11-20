import { router } from '@inertiajs/vue3';
import type { File } from '@/types/schema';

/**
 * Navigate to a folder by ID
 */
export function openFolder(folderId: string, basePath: string = '/records'): void {
    router.visit(`${basePath}/${folderId}`);
}

/**
 * Navigate to a file by ID with validation
 */
export function openFile(
    fileId: string,
    file?: File,
    onError?: (title: string, message: string) => void,
): void {
    const hasFile = file ? Boolean(file.file_path) : true;
    const isProcessing = file
        ? file.assembly_status === 'processing' || file.ocr_status === 'pending' || file.ocr_status === 'processing'
        : false;

    if (!hasFile && !isProcessing && onError) {
        onError('No file attached', 'Attach a file to this record before viewing.');
        return;
    }

    router.visit(`/file/${fileId}/view`);
}
