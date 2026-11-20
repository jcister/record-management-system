/**
 * Retention calculation utilities for client-side retention status management
 */

export type RetentionDuration = 'one_year' | 'ten_years' | 'perpetual';
export type RetentionStatus = 'active' | 'archived' | 'ready_for_disposal' | 'disposed';

/**
 * Parse a date string in MM/DD/YY or MM/DD/YYYY format to ISO format
 */
export function parseShortDate(dateStr: string): string | null {
    if (!dateStr) {
        return null;
    }

    // Accept both MM/DD/YY (8 chars) and MM/DD/YYYY (10 chars)
    if (dateStr.length !== 8 && dateStr.length !== 10) {
        return null;
    }

    const parts = dateStr.split('/');
    if (parts.length !== 3) {
        return null;
    }

    const month = parts[0].padStart(2, '0');
    const day = parts[1].padStart(2, '0');
    let year = parseInt(parts[2], 10);

    // Handle 2-digit year (convert to 4-digit)
    if (parts[2].length === 2) {
        if (year < 50) {
            year += 2000;
        } else if (year < 100) {
            year += 1900;
        }
    }

    return `${year}-${month}-${day}`;
}

/**
 * Calculate the retention expiration date based on date released and duration
 */
export function calculateRetentionExpiresAt(
    dateReleased: string | null,
    duration: RetentionDuration | '' | null,
): string | null {
    if (!dateReleased || !duration || duration === 'perpetual') {
        return null;
    }

    const isoDate = parseShortDate(dateReleased);
    if (!isoDate) {
        return null;
    }

    try {
        const date = new Date(isoDate);
        if (isNaN(date.getTime())) {
            return null;
        }

        const years = duration === 'one_year' ? 1 : duration === 'ten_years' ? 10 : 0;
        if (years === 0) {
            return null;
        }

        date.setFullYear(date.getFullYear() + years);
        return date.toISOString().split('T')[0];
    } catch {
        return null;
    }
}

/**
 * Calculate retention status based on expiration date
 */
export function calculateRetentionStatus(retentionExpiresAt: string | null): RetentionStatus {
    if (!retentionExpiresAt) {
        return 'active';
    }

    try {
        const expiryDate = new Date(retentionExpiresAt);
        if (isNaN(expiryDate.getTime())) {
            return 'active';
        }

        // Set to end of day for comparison
        expiryDate.setHours(23, 59, 59, 999);

        const now = new Date();
        return expiryDate < now ? 'ready_for_disposal' : 'active';
    } catch {
        return 'active';
    }
}

/**
 * Calculate retention status dynamically based on date released and duration
 */
export function calculateDynamicRetentionStatus(
    dateReleased: string | null,
    duration: RetentionDuration | '' | null,
): RetentionStatus {
    const expiresAt = calculateRetentionExpiresAt(dateReleased, duration);
    return calculateRetentionStatus(expiresAt);
}
