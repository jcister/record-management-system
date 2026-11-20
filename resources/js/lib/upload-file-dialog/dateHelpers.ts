import { parseDate, type DateValue } from '@internationalized/date';

const SHORT_DATE_PATTERN = /^\d{2}\/\d{2}\/(\d{2}|\d{4})$/;

const toFourDigitYear = (year: number): number => {
    if (year < 50) {
        return year + 2000;
    }

    if (year < 100) {
        return year + 1900;
    }

    return year;
};

export const parseShortDateInput = (value?: string | null): DateValue | undefined => {
    if (!value || !SHORT_DATE_PATTERN.test(value)) {
        return undefined;
    }

    const [monthPart, dayPart, yearPart] = value.split('/');
    const month = monthPart.padStart(2, '0');
    const day = dayPart.padStart(2, '0');

    let year: number;
    if (yearPart.length === 2) {
        year = toFourDigitYear(Number.parseInt(yearPart, 10));
    } else {
        year = Number.parseInt(yearPart, 10);
    }

    if (Number.isNaN(year)) {
        return undefined;
    }

    try {
        return parseDate(`${year}-${month}-${day}`);
    } catch {
        return undefined;
    }
};

export const toShortDateInput = (value?: string | null): string => {
    if (!value) {
        return '';
    }

    const trimmed = value.trim();

    // If already MM/DD/YY or MM/DD/YYYY, normalize to MM/DD/YYYY
    const mmddyy = /^\d{2}\/\d{2}\/\d{2}$/;
    const mmddyyyy = /^\d{2}\/\d{2}\/\d{4}$/;
    if (mmddyyyy.test(trimmed)) {
        return trimmed;
    }

    if (mmddyy.test(trimmed)) {
        // Expand two-digit year to four digits using pivot
        const [m, d, y] = trimmed.split('/');
        const year = toFourDigitYear(Number.parseInt(y, 10));
        return `${m}/${d}/${String(year)}`;
    }

    // Match ISO date format (YYYY-MM-DD) - with or without time/timezone
    // This handles: "1988-02-04", "1988-02-04T00:00:00", "1988-02-04T00:00:00+08:00", etc.
    const isoMatch = trimmed.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (isoMatch) {
        const [, year, month, day] = isoMatch;
        // Remove leading zeros for display (02 -> 2, but keep as 02 if needed)
        return `${month}/${day}/${year}`;
    }

    // Last resort fallback: try to parse and extract components
    // Use a safer approach by creating a Date with explicit local time to avoid UTC conversion
    try {
        // Create date in local timezone by appending T00:00:00
        const dateWithTime = trimmed.includes('T') ? trimmed : `${trimmed}T00:00:00`;
        const parsed = new Date(dateWithTime);

        if (!Number.isNaN(parsed.getTime())) {
            const month = String(parsed.getMonth() + 1).padStart(2, '0');
            const day = String(parsed.getDate()).padStart(2, '0');
            const year = String(parsed.getFullYear());
            return `${month}/${day}/${year}`;
        }
    } catch {
        // If parsing fails, return original value
    }

    return trimmed;
};

export const isReleasedBeforeCreated = (
    dateCreated?: string | null,
    dateReleased?: string | null,
): boolean => {
    const created = parseShortDateInput(dateCreated);
    const released = parseShortDateInput(dateReleased);

    if (!created || !released) {
        return false;
    }

    return released.compare(created) < 0;
};
