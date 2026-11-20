export type RetentionStatus = 'active' | 'ready_for_disposal' | 'disposed' | 'archived' | string | null | undefined;

export const retentionStatusLabel = (status: RetentionStatus): string => {
    const value = (status ?? '').toString();

    switch (value) {
        case 'active':
            return 'Active';
        case 'ready_for_disposal':
            return 'Ready for Disposal';
        case 'disposed':
            return 'Disposed';
        case 'archived':
            return 'Archived';
        default:
            return value ? value.replace(/_/g, ' ') : 'Unknown';
    }
};

export const retentionStatusBadgeClass = (status: RetentionStatus): string => {
    const value = (status ?? '').toString();

    switch (value) {
        case 'active':
            return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300';
        case 'ready_for_disposal':
            return 'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-800 dark:bg-orange-900/40 dark:text-orange-300';
        case 'disposed':
            return 'border-red-200 bg-red-50 text-red-700 dark:border-red-800 dark:bg-red-900/40 dark:text-red-300';
        case 'archived':
            return 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-800 dark:bg-gray-900/40 dark:text-gray-300';
        default:
            return 'border-muted-foreground/30 text-muted-foreground';
    }
};
