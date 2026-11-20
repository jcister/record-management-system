export type FileCategory =
    | 'image'
    | 'video'
    | 'audio'
    | 'pdf'
    | 'document'
    | 'spreadsheet'
    | 'presentation'
    | 'text'
    | 'archive'
    | 'unknown';

const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'heic'];
const videoExtensions = ['mp4', 'mov', 'avi', 'mkv', 'webm', 'wmv'];
const audioExtensions = ['mp3', 'wav', 'aac', 'flac', 'm4a', 'ogg'];
const documentExtensions = ['doc', 'docx', 'docm', 'dot', 'dotx', 'dotm', 'rtf', 'odt', 'pages'];
const spreadsheetExtensions = ['xls', 'xlsx', 'xlsm', 'xlsb', 'csv', 'ods', 'numbers'];
const presentationExtensions = ['ppt', 'pptx', 'pptm', 'pot', 'potx', 'potm', 'pps', 'ppsx', 'key', 'odp'];
const textExtensions = ['txt', 'md', 'json', 'xml', 'html', 'css', 'js', 'csv'];
const archiveExtensions = ['zip', 'rar', '7z', 'tar', 'gz'];

const hasExtension = (exts: string[], extension: string): boolean => {
    return extension !== '' && exts.includes(extension);
};

const normalizeMime = (mimeType?: string | null): string => (mimeType || '').toLowerCase();

export const getExtension = (filename?: string | null): string => {
    if (!filename || !filename.includes('.')) {
        return '';
    }

    return filename.split('.').pop()?.toLowerCase() ?? '';
};

export const detectFileCategory = (mimeType?: string | null, filename?: string | null): FileCategory => {
    const normalizedMime = normalizeMime(mimeType);
    const extension = getExtension(filename);

    if (normalizedMime.startsWith('image/') || hasExtension(imageExtensions, extension)) {
        return 'image';
    }

    if (normalizedMime.startsWith('video/') || hasExtension(videoExtensions, extension)) {
        return 'video';
    }

    if (normalizedMime.startsWith('audio/') || hasExtension(audioExtensions, extension)) {
        return 'audio';
    }

    if (normalizedMime === 'application/pdf' || extension === 'pdf') {
        return 'pdf';
    }

    if (
        normalizedMime.includes('word') ||
        normalizedMime.includes('msword') ||
        normalizedMime.includes('officedocument.wordprocessingml') ||
        normalizedMime === 'application/msword' ||
        normalizedMime === 'application/vnd.ms-word' ||
        hasExtension(documentExtensions, extension)
    ) {
        return 'document';
    }

    if (
        normalizedMime.includes('sheet') ||
        normalizedMime.includes('excel') ||
        normalizedMime.includes('spreadsheet') ||
        normalizedMime.includes('numbers') ||
        normalizedMime === 'application/vnd.ms-excel.sheet.macroenabled.12' ||
        normalizedMime === 'application/vnd.ms-excel.sheet.binary.macroenabled.12' ||
        normalizedMime === 'application/vnd.ms-excel' ||
        hasExtension(spreadsheetExtensions, extension)
    ) {
        return 'spreadsheet';
    }

    if (
        normalizedMime.includes('presentation') ||
        normalizedMime.includes('powerpoint') ||
        normalizedMime.includes('keynote') ||
        normalizedMime === 'application/vnd.ms-powerpoint' ||
        normalizedMime === 'application/vnd.ms-powerpoint.presentation.macroenabled.12' ||
        hasExtension(presentationExtensions, extension)
    ) {
        return 'presentation';
    }

    if (
        normalizedMime.startsWith('text/') ||
        ['application/json', 'application/xml', 'text/xml', 'text/csv', 'application/javascript'].includes(normalizedMime) ||
        hasExtension(textExtensions, extension)
    ) {
        return 'text';
    }

    if (
        normalizedMime.includes('zip') ||
        normalizedMime.includes('rar') ||
        normalizedMime.includes('tar') ||
        normalizedMime.includes('compression') ||
        hasExtension(archiveExtensions, extension)
    ) {
        return 'archive';
    }

    return 'unknown';
};
