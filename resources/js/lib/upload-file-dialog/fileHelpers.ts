import { detectFileCategory } from '@/utils/fileTypes';
import { stripExtension } from '@/utils/upload';

import type { UploadDialogMode } from './types';

export const normalizeFileNameInput = (value?: string | null): string => {
    const trimmed = value?.trim() ?? '';
    if (!trimmed) {
        return '';
    }

    // Only strip a trailing extension when the last segment looks like
    // a real file extension (short, alphanumeric, contains a letter).
    // This prevents numeric dotted suffixes (e.g. ".2022") from being
    // treated as extensions and stripped.
    const lastDot = trimmed.lastIndexOf('.');
    if (lastDot > 0) {
        const possibleExt = trimmed.slice(lastDot + 1);
        if (/^(?=.*[A-Za-z])[A-Za-z0-9]{1,8}$/.test(possibleExt)) {
            return stripExtension(trimmed);
        }
    }

    return trimmed;
};

export const selectedFileBaseName = (file: File | null): string => {
    // Use the same normalization logic as `normalizeFileNameInput` so that
    // programmatic checks match UI normalization (avoid truncating at
    // internal dots).
    return normalizeFileNameInput(file?.name ?? '');
};

export const normalizeFileNameBase = (value?: string | null): string => {
    return normalizeFileNameInput(value)?.toLowerCase();
};

export type MatchableRecord = {
    id: string;
    file_name?: string | null;
};

export type RecordMatchStatus = 'idle' | 'matched' | 'ambiguous' | 'none';

export const findRecordsMatchingFileName = <T extends MatchableRecord>(
    records: T[],
    file: File | null,
): T[] => {
    if (!file || records.length === 0) {
        return [];
    }

    const normalizedFile = normalizeFileNameBase(selectedFileBaseName(file));
    if (!normalizedFile) {
        return [];
    }

    return records.filter((record) => {
        return normalizeFileNameBase(record.file_name) === normalizedFile;
    });
};

export const resolveRecordAutoSelection = <T extends MatchableRecord>(
    records: T[],
    file: File | null,
): { selectedRecordId: string; matchStatus: RecordMatchStatus } => {
    if (records.length === 0) {
        return { selectedRecordId: '', matchStatus: 'idle' };
    }

    if (!file) {
        return records.length === 1
            ? { selectedRecordId: records[0].id, matchStatus: 'idle' }
            : { selectedRecordId: '', matchStatus: 'idle' };
    }

    const matches = findRecordsMatchingFileName(records, file);
    if (matches.length === 1) {
        return { selectedRecordId: matches[0].id, matchStatus: 'matched' };
    }

    if (matches.length > 1) {
        return { selectedRecordId: '', matchStatus: 'ambiguous' };
    }

    return { selectedRecordId: '', matchStatus: 'none' };
};

export const doesSelectedFileMatchRecordName = (
    file: File | null,
    recordFileName?: string | null,
): boolean => {
    if (!file) {
        return true;
    }

    return normalizeFileNameBase(selectedFileBaseName(file)) === normalizeFileNameBase(recordFileName);
};

export const buildUppercaseNamedFile = (file: File): File => {
    const uppercasedName = file.name.toUpperCase();
    if (uppercasedName === file.name) {
        return file;
    }

    return new File([file], uppercasedName, { type: file.type, lastModified: file.lastModified });
};

export const resolveUploadMode = (
    selectedFile: File | null,
    fallbackMode: UploadDialogMode,
): UploadDialogMode => {
    if (!selectedFile) {
        return fallbackMode;
    }

    const category = detectFileCategory(selectedFile.type, selectedFile.name);
    return category === 'pdf' || category === 'image' ? 'record' : 'fileOnly';
};
