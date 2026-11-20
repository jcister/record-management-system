import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import { type File as FileRecord } from '@/types/schema';
import { stripExtension } from '@/utils/upload';
import type { ConfirmOptions } from '@/composables/useConfirm';

interface DuplicateResolution {
    file?: File;
    handled?: boolean;
}

interface DuplicateResolverOptions {
    showConfirm: (options: ConfirmOptions) => Promise<'confirm' | 'replace' | 'cancel'>;
    showError: (title: string, message: string) => void;
    onReplace: (file: File, conflict: FileRecord) => Promise<void>;
    skipRecordPredicate?: (record: FileRecord) => boolean;
}

export const useDuplicateFileResolver = (options: DuplicateResolverOptions) => {
    const page = usePage();
    const existingFiles = computed<FileRecord[]>(() => ((page.props as any).files ?? []) as FileRecord[]);
    const shouldSkipRecord = options.skipRecordPredicate ?? ((record: FileRecord) => Boolean(record.pending_scan_metadata));

    const normalizeBaseName = (name: string): string => stripExtension(name).toLowerCase();
    const normalizeFullName = (name: string): string => name.toLowerCase();
    const matchesExistingRecord = (record: FileRecord, baseName: string, fullName: string): boolean => {
        const recordFull = (record.file_name ?? '').toLowerCase();
        const recordBase = normalizeBaseName(record.file_name ?? '');

        return (recordBase !== '' && recordBase === baseName) || (recordFull !== '' && recordFull === fullName);
    };

    const checkDuplicateFileName = async (file: File, folderId: string | null): Promise<DuplicateResolution> => {
        const normalizedBaseName = normalizeBaseName(file.name);
        const normalizedFullName = normalizeFullName(file.name);

        const siblings = existingFiles.value.filter((record) => {
            const sameFolder = (record.folder_id ?? null) === (folderId ?? null);
            return sameFolder && !shouldSkipRecord(record);
        });

        const conflict = siblings.find((record) => matchesExistingRecord(record, normalizedBaseName, normalizedFullName));

        if (!conflict) {
            return { file };
        }

        const userChoice = await options.showConfirm({
            title: 'Duplicate File Name',
            description: `"${file.name}" already exists in this folder. Replace the existing file or keep both?`,
            replaceText: 'Replace existing',
            confirmText: 'Keep both (rename)',
            cancelText: 'Skip',
        });

        if (userChoice === 'cancel') {
            throw new Error('Upload cancelled by user');
        }

        if (userChoice === 'replace') {
            const conflictFull = (conflict.file_name ?? '').toLowerCase();
            const incomingBase = normalizedBaseName;
            const incomingFull = normalizedFullName;

            const matchesBase = conflictFull !== '' && normalizeBaseName(conflict.file_name ?? '') === incomingBase;
            const matchesFull = conflictFull !== '' && incomingFull === conflictFull;

            if (!matchesBase && !matchesFull) {
                const targetName = conflict.file_name;
                options.showError('File name mismatch', `Rename your file to "${targetName}" before replacing it.`);
                return { handled: true };
            }

            await options.onReplace(file, conflict);
            return { handled: true };
        }

        const nameParts = file.name.match(/^(.+?)(\.[^.]*)?$/);
        const baseName = nameParts ? nameParts[1] : file.name;
        const extension = nameParts && nameParts[2] ? nameParts[2] : '';

        let counter = 1;
        let newFileName = `${baseName} (${counter})${extension}`;
        let normalizedNewBase = normalizeBaseName(newFileName);
        let normalizedNewFull = normalizeFullName(newFileName);

        while (siblings.some((record) => matchesExistingRecord(record, normalizedNewBase, normalizedNewFull))) {
            counter++;
            newFileName = `${baseName} (${counter})${extension}`;
            normalizedNewBase = normalizeBaseName(newFileName);
            normalizedNewFull = normalizeFullName(newFileName);
        }

        return { file: new File([file], newFileName, { type: file.type }) };
    };

    return { checkDuplicateFileName };
};
