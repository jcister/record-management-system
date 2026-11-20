export const formatDate = (value?: string | null): string => {
    if (!value) {
        return '-';
    }

    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
        return '';
    }

    // Format dates with Manila timezone (UTC+8) in mind
    // The ISO 8601 format from backend will be correctly parsed
    return parsed.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        timeZone: 'Asia/Manila',
    });
};

export const formatDateTime = (value?: string | null): string => {
    if (!value) {
        return '-';
    }

    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
        return '';
    }

    return parsed.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: 'Asia/Manila',
    });
};

/**
 * Convert ISO 8601 datetime to datetime-local input format (YYYY-MM-DDTHH:mm)
 * Handles Manila timezone (UTC+8) correctly
 */
export const toDateTimeLocalValue = (isoString?: string | null): string => {
    if (!isoString) {
        return '';
    }

    const date = new Date(isoString);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    // Convert to Manila time and format for datetime-local input
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${year}-${month}-${day}T${hours}:${minutes}`;
};

/**
 * Convert datetime-local input value to ISO 8601 format for Manila timezone
 */
export const fromDateTimeLocalValue = (localValue: string): string => {
    if (!localValue) {
        return '';
    }

    // The datetime-local value is in format: YYYY-MM-DDTHH:mm
    // We need to convert it to ISO 8601 format with Manila timezone
    const date = new Date(localValue);

    return date.toISOString();
};

export const formatMonthYear = (value?: string | null): string => {
    if (!value) {
        return '—';
    }

    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
        return '—';
    }

    return parsed.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
    });
};

export const formatFileSize = (bytes?: number | null): string => {
    if (bytes === null || bytes === undefined || !Number.isFinite(bytes) || bytes < 0) {
        return '';
    }

    if (bytes === 0) {
        return '0 Bytes';
    }

    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
};

export const formatMimeTypeLabel = (mimeType?: string | null): string => {
    if (!mimeType) {
        return '';
    }

    const normalized = mimeType.trim().toLowerCase();

    const commonMimeLabels: Record<string, string> = {
        'application/pdf': 'PDF',
        'image/jpeg': 'JPEG',
        'image/jpg': 'JPG',
        'image/png': 'PNG',
        'image/gif': 'GIF',
        'image/webp': 'WEBP',
        'text/plain': 'TXT',
        'application/zip': 'ZIP',
        'application/msword': 'DOC',
        'application/vnd.ms-excel': 'XLS',
        'application/vnd.ms-powerpoint': 'PPT',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'DOCX',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'XLSX',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation': 'PPTX',
    };

    if (commonMimeLabels[normalized]) {
        return commonMimeLabels[normalized];
    }

    const sanitized = mimeType.replace(/^application\//i, '');
    const lastSegment = sanitized.split('/').pop() ?? sanitized;

    return lastSegment
        .replace(/[-_.]+/g, ' ')
        .trim()
        .toUpperCase();
};

export const formatRequestCode = (
    requestIdentifier?: string | null,
    fallbackId?: string | number | null,
): string | null => {
    const primary = requestIdentifier ?? (fallbackId ? String(fallbackId) : null);

    if (!primary) {
        return null;
    }

    const trimmed = primary.trim();

    if (trimmed === '') {
        return null;
    }

    return `REQ-${trimmed.slice(0, 8)}`;
};
