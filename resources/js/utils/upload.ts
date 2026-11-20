export const DISALLOWED_ROOT_PATHS = ['/dashboard', '/records', '/records/pending-unorganized'];

export const stripExtension = (fileName: string): string => {
    if (!fileName) {
        return '';
    }

    const trimmed = fileName.trim();

    if (trimmed === '') {
        return '';
    }

    const lastDot = trimmed.lastIndexOf('.');

    if (lastDot <= 0) {
        return trimmed;
    }

    return trimmed.slice(0, lastDot);
};

export const buildRecordId = (fileName: string): string => {
    const base = stripExtension(fileName)
        .replace(/[^A-Za-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '') || 'file';

    const recordId = `${base}-${Date.now()}`;

    return recordId.slice(0, 255);
};

export const getDefaultAuthor = (pageProps: any): string => pageProps?.auth?.user?.name ?? '';

export const ensureFolderUploadTarget = (
    pageProps: any,
    currentPath: string,
    showError: (title: string, message: string) => void,
    disallowedPaths: string[] = DISALLOWED_ROOT_PATHS,
): string | null => {
    const currentFolder = (pageProps as any)?.folder;

    if (!currentFolder?.id || disallowedPaths.includes(currentPath)) {
        showError('Cannot Upload Here', 'Files can only be uploaded inside folders. Please open a folder first.');

        return null;
    }

    return currentFolder.id as string;
};
