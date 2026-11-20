<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Archive,
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    CheckCircle2,
    Circle,
    ClipboardList,
    Eye,
    Info,
    Layers,
    X,
} from 'lucide-vue-next';
import {
    computed,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from 'vue';

import AlertError from '@/components/common/AlertError.vue';
import InputError from '@/components/common/InputError.vue';
import DisposalFilterComponent from '@/components/disposal/DisposalFilterComponent.vue';
import ItemMetadataSheet from '@/components/records/ItemMetadataSheet.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import {
    formatDate,
    formatFileSize,
    toDateTimeLocalValue,
} from '@/utils/format';

type FolderCrumb = { id: string; name: string };
type LinkedModel = { id: string; name: string | null };

interface DisposalRecord {
    id: string;
    record_id: string;
    file_name: string;
    author: string;
    file_path: string | null;
    folder: {
        trail: FolderCrumb[];
        root: FolderCrumb | null;
    };
    institution: LinkedModel | null;
    department: { id: string | number; name: string } | null;
    uploader: LinkedModel | null;
    retention: {
        duration: string | null;
        expires_at: string | null;
    };
    physical: {
        location: string | null;
        tags: { id: number; name: string }[];
    };
    electronic: {
        version_count: number;
        latest_version: number | null;
        current_version: {
            name: string | null;
            size: number | null;
            mime_type: string | null;
            version_number: number | null;
        };
    };
    date_created: string | null;
    date_released: string | null;
    retention_status: string;
    urgency: string;
    days_until_expiry: number | null;
}

interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
}

type FilterForm = {
    search: string;
};

type ValidationErrorBag = Record<string, string | string[] | undefined>;

interface Options {
    root_folders: { id: string; name: string }[];
    retention_durations: {
        value: string;
        label: string;
        description: string;
    }[];
    per_page: number[];
    departments: { id: string | number; name: string }[];
    mode_of_disposal: { value: string; label: string }[];
}

interface Metrics {
    totals: {
        active: number;
        ready_for_disposal: number;
        disposed: number;
        scheduled_batches: number;
    };
    upcoming: {
        next_30_days: number;
        next_90_days: number;
        overdue: number;
    };
    urgency: {
        all: number;
        immediate: number;
        soon: number;
        scheduled: number;
        perpetual: number;
    };
}

type Props = {
    queue: Paginator<DisposalRecord>;
    filters: {
        search?: string | null;
        urgency?: string | null;
        per_page?: number | null;
        retention_duration?: string | null;
        root_folder_id?: string | null;
        department_id?: string | null;
        expires_from?: string | null;
        expires_to?: string | null;
        released_from?: string | null;
        released_to?: string | null;
        sort_by?: string | null;
        sort_direction?: string | null;
    };
    options: Options;
    metrics: Metrics;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Disposal Queue',
        description:
            'Manage Records Approaching Retention Expiration and Ready for Disposal',
        href: '/disposal-management',
    },
];

const filterState = reactive<FilterForm>({
    search: props.filters.search ?? '',
});

// Debounced search - send to backend for full database search
let searchTimeout: ReturnType<typeof setTimeout> | null = null;
watch(
    () => filterState.search,
    (newSearch) => {
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }

        searchTimeout = setTimeout(() => {
            const currentUrl = new URL(window.location.href);
            const params = new URLSearchParams(currentUrl.search);

            if (newSearch && newSearch.trim()) {
                params.set('search', newSearch.trim());
            } else {
                params.delete('search');
            }

            // Reset to page 1 when searching
            params.delete('page');

            const newUrl = `${currentUrl.pathname}?${params.toString()}`;
            router.visit(newUrl, {
                preserveState: true,
                preserveScroll: false,
                only: ['queue', 'filters'],
            });
        }, 400); // 400ms debounce
    },
);

// Additional filter states for new filters - initialize from props
const showFilters = ref(false);
const urgencyFilter = ref(props.filters.urgency ?? '');
const retentionDurationFilter = ref(props.filters.retention_duration ?? '');
const rootFolderFilter = ref(props.filters.root_folder_id ?? '');
const departmentFilter = ref(props.filters.department_id ?? '');
const expiresFromFilter = ref(props.filters.expires_from ?? '');
const expiresToFilter = ref(props.filters.expires_to ?? '');
const releasedFromFilter = ref(props.filters.released_from ?? '');
const releasedToFilter = ref(props.filters.released_to ?? '');

// Sorting state
type SortField =
    | 'record_id'
    | 'file_name'
    | 'author'
    | 'date_released'
    | 'retention_expires'
    | 'urgency';
type SortDirection = 'asc' | 'desc';
const sortBy = ref<SortField>(
    (props.filters.sort_by as SortField) || 'urgency',
);
const sortDirection = ref<SortDirection>(
    (props.filters.sort_direction as SortDirection) || 'asc',
);

const suppressPerPageWatch = ref(false);
const perPage = ref(
    String(props.filters.per_page ?? props.options.per_page[0]),
);

const { success, error } = useToast();
const isSubmitting = ref(false);

// Storage key for persisting selection across page navigation
const SELECTION_STORAGE_KEY = 'disposal-management-selection';

const hasSessionStorage = () =>
    typeof window !== 'undefined' && typeof sessionStorage !== 'undefined';

const clearSelectionStorage = () => {
    if (!hasSessionStorage()) {
        return;
    }

    try {
        sessionStorage.removeItem(SELECTION_STORAGE_KEY);
    } catch (e) {
        console.error('Failed to clear selection storage:', e);
    }
};

const shouldResetSelectionOnLoad = () => {
    if (typeof window === 'undefined' || typeof performance === 'undefined') {
        return false;
    }

    const [navigationEntry] =
        performance.getEntriesByType?.('navigation') ?? [];
    return navigationEntry?.type === 'reload';
};

// Initialize selection from sessionStorage if available
const loadSelectionFromStorage = (): Set<string> => {
    if (!hasSessionStorage()) {
        return new Set();
    }

    try {
        const stored = sessionStorage.getItem(SELECTION_STORAGE_KEY);
        if (stored) {
            return new Set(JSON.parse(stored));
        }
    } catch (e) {
        console.error('Failed to load selection from storage:', e);
    }
    return new Set();
};

const selection = ref<Set<string>>(loadSelectionFromStorage());

// Save selection to sessionStorage whenever it changes
watch(
    () => selection.value,
    (newSelection) => {
        if (!hasSessionStorage()) {
            return;
        }

        try {
            if (newSelection.size === 0) {
                sessionStorage.removeItem(SELECTION_STORAGE_KEY);
            } else {
                sessionStorage.setItem(
                    SELECTION_STORAGE_KEY,
                    JSON.stringify(Array.from(newSelection)),
                );
            }
        } catch (e) {
            console.error('Failed to save selection to storage:', e);
        }
    },
    { deep: true },
);

// Info sheet states
const infoSheetOpen = ref(false);
const selectedFileId = ref<string | null>(null);
const selectedFileName = ref<string>('');

// Dialog states
const scheduleDialogOpen = ref(false);
const completeDialogOpen = ref(false);
const scheduleForm = reactive({
    scheduled_at: '',
    note: '',
    mode_of_disposal: '',
});
const scheduleFieldErrors = reactive<{
    scheduled_at: string;
    mode_of_disposal: string;
    note: string;
}>({
    scheduled_at: '',
    mode_of_disposal: '',
    note: '',
});
const scheduleErrorMessages = ref<string[]>([]);

const toSentenceCase = (value: string): string => {
    const trimmed = value.trim();

    if (!trimmed) {
        return '';
    }

    const chars = trimmed.split('');
    let shouldCapitalize = true;

    for (let index = 0; index < chars.length; index += 1) {
        const currentChar = chars[index];

        if (/[a-z]/.test(currentChar) && shouldCapitalize) {
            chars[index] = currentChar.toUpperCase();
            shouldCapitalize = false;
            continue;
        }

        if (/[A-Z]/.test(currentChar)) {
            shouldCapitalize = false;
            continue;
        }

        if (/[.!?]/.test(currentChar) || currentChar === '\n') {
            shouldCapitalize = true;
        }
    }

    return chars.join('');
};

const applyScheduleNoteSentenceCase = (): void => {
    scheduleForm.note = toSentenceCase(scheduleForm.note);
};

const resetScheduleErrors = () => {
    scheduleErrorMessages.value = [];
    scheduleFieldErrors.scheduled_at = '';
    scheduleFieldErrors.mode_of_disposal = '';
    scheduleFieldErrors.note = '';
};
const friendlyFieldMessage = (field: string, message: string): string => {
    const normalized = message?.trim();
    if (!normalized) {
        return '';
    }

    const lower = normalized.toLowerCase();

    if (field === 'scheduled_at') {
        if (lower.includes('date after or equal to now')) {
            return 'Pick a date and time that is at least a few minutes in the future.';
        }
        if (lower.includes('date after') && lower.includes('current time')) {
            return 'Pick a future date and time for the schedule.';
        }
    }

    return normalized;
};
const firstErrorMessage = (
    field: string,
    value?: string | string[],
): string => {
    if (Array.isArray(value)) {
        const first = value.find((entry) => Boolean(entry)) ?? '';
        return friendlyFieldMessage(field, first);
    }

    return friendlyFieldMessage(field, value ?? '');
};
const aggregateErrorMessages = (errors: ValidationErrorBag): string[] => {
    const collected: string[] = [];

    Object.entries(errors).forEach(([field, value]) => {
        if (Array.isArray(value)) {
            value.forEach((entry) => {
                const friendly = friendlyFieldMessage(field, entry ?? '');
                if (friendly) {
                    collected.push(friendly);
                }
            });
        } else if (value) {
            const friendly = friendlyFieldMessage(field, value);
            if (friendly) {
                collected.push(friendly);
            }
        }
    });

    return Array.from(new Set(collected));
};
const completeForm = reactive({
    completed_at: '',
});

const disposalModeOptions = computed(
    () => props.options.mode_of_disposal ?? [],
);

// Client-side filtering is removed - all filtering now handled by backend
// Display the data returned from the server directly
const filteredQueue = computed(() => props.queue.data);

// Minimum scheduled date (current time in user's timezone)
const minScheduledDate = computed(() => {
    return toDateTimeLocalValue(new Date().toISOString());
});

// Watch for filter changes and send to backend
watch(
    [
        urgencyFilter,
        retentionDurationFilter,
        rootFolderFilter,
        departmentFilter,
        expiresFromFilter,
        expiresToFilter,
        releasedFromFilter,
        releasedToFilter,
    ],
    () => {
        const currentUrl = new URL(window.location.href);
        const params = new URLSearchParams(currentUrl.search);

        // Set urgency filter
        if (urgencyFilter.value && urgencyFilter.value !== 'all') {
            params.set('urgency', urgencyFilter.value);
        } else {
            params.delete('urgency');
        }

        // Set retention duration filter
        if (retentionDurationFilter.value) {
            params.set('retention_duration', retentionDurationFilter.value);
        } else {
            params.delete('retention_duration');
        }

        // Set folder filter
        if (rootFolderFilter.value) {
            params.set('root_folder_id', rootFolderFilter.value);
        } else {
            params.delete('root_folder_id');
        }

        // Set department filter
        if (departmentFilter.value) {
            params.set('department_id', departmentFilter.value);
        } else {
            params.delete('department_id');
        }

        // Set expiry date filters
        if (expiresFromFilter.value) {
            params.set('expires_from', expiresFromFilter.value);
        } else {
            params.delete('expires_from');
        }

        if (expiresToFilter.value) {
            params.set('expires_to', expiresToFilter.value);
        } else {
            params.delete('expires_to');
        }

        // Set released date filters
        if (releasedFromFilter.value) {
            params.set('released_from', releasedFromFilter.value);
        } else {
            params.delete('released_from');
        }

        if (releasedToFilter.value) {
            params.set('released_to', releasedToFilter.value);
        } else {
            params.delete('released_to');
        }

        // Reset to page 1 when filters change
        params.delete('page');

        const newUrl = `${currentUrl.pathname}?${params.toString()}`;
        router.visit(newUrl, {
            preserveState: true,
            preserveScroll: false,
            only: ['queue', 'filters', 'metrics'],
        });
    },
    { deep: true },
);

// Watch perPage changes and trigger backend request
watch(perPage, (newPerPage) => {
    if (suppressPerPageWatch.value) {
        return;
    }

    const currentUrl = new URL(window.location.href);
    const params = new URLSearchParams(currentUrl.search);
    params.set('per_page', newPerPage);

    // Reset to page 1 when changing page size
    params.delete('page');

    const newUrl = `${currentUrl.pathname}?${params.toString()}`;
    router.visit(newUrl, {
        preserveState: true,
        preserveScroll: false,
        only: ['queue', 'filters'],
    });
});

const selectedVisibleRecords = computed(() =>
    filteredQueue.value.filter((record) => selection.value.has(record.id)),
);

const offPageSelectionCount = computed(
    () => selection.value.size - selectedVisibleRecords.value.length,
);
const hasOffPageSelections = computed(() => offPageSelectionCount.value > 0);
const selectionPreview = computed(() =>
    selectedVisibleRecords.value.map((record) => record.id),
);

onMounted(() => {
    if (shouldResetSelectionOnLoad()) {
        selection.value = new Set();
        clearSelectionStorage();
    }

    if (typeof window !== 'undefined') {
        window.addEventListener('beforeunload', clearSelectionStorage);
    }
});

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('beforeunload', clearSelectionStorage);
    }
});

const durationLookup = computed<Record<string, string>>(() => {
    const lookup: Record<string, string> = {};
    props.options.retention_durations.forEach((option) => {
        lookup[option.value] = option.label;
    });
    return lookup;
});

const hasActiveFilters = computed(() => {
    return !!(
        filterState.search ||
        (urgencyFilter.value && urgencyFilter.value !== 'all') ||
        retentionDurationFilter.value ||
        rootFolderFilter.value ||
        departmentFilter.value ||
        expiresFromFilter.value ||
        expiresToFilter.value ||
        releasedFromFilter.value ||
        releasedToFilter.value
    );
});

const formattedTrail = (trail: FolderCrumb[]): string => {
    if (!trail.length) {
        return 'Unfiled';
    }
    return trail.map((crumb) => crumb.name).join(' / ');
};

const retentionLabel = (value: string | null): string => {
    if (!value) return 'No retention set';
    return durationLookup.value[value] ?? value;
};

const retentionBadgeClass = (value: string | null): string => {
    switch (value) {
        case 'perpetual':
            return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300';
        case 'ten_years':
            return 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-900/40 dark:text-blue-300';
        case 'one_year':
            return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-900/40 dark:text-amber-300';
        default:
            return 'border-muted-foreground/40 text-muted-foreground';
    }
};

const formatPhysicalLocation = (
    tags: { id: number; name: string }[],
): string => {
    if (!tags || tags.length === 0) return 'N/A';
    return tags.map((tag) => tag.name).join(', ');
};

const handleSort = (field: SortField) => {
    if (sortBy.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = field;
        sortDirection.value = 'asc';
    }

    const currentUrl = new URL(window.location.href);
    const params = new URLSearchParams(currentUrl.search);
    params.set('sort_by', sortBy.value);
    params.set('sort_direction', sortDirection.value);
    params.delete('page');

    const newUrl = `${currentUrl.pathname}?${params.toString()}`;
    router.visit(newUrl, {
        preserveState: true,
        preserveScroll: false,
        only: ['queue', 'filters'],
    });
};

const getSortIcon = (field: SortField) => {
    if (sortBy.value !== field) return ArrowUpDown;
    return sortDirection.value === 'asc' ? ArrowUp : ArrowDown;
};

const getUrgencyConfig = (urgency: string) => {
    const configs: Record<
        string,
        {
            label: string;
            class: string;
        }
    > = {
        immediate: {
            label: 'Immediate Action',
            class: 'border-red-200 bg-red-50 text-red-700 dark:border-red-800 dark:bg-red-900/40 dark:text-red-300',
        },
        soon: {
            label: 'Action Soon',
            class: 'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-800 dark:bg-orange-900/40 dark:text-orange-300',
        },
        scheduled: {
            label: 'Scheduled',
            class: 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
        },
        perpetual: {
            label: 'Perpetual',
            class: 'border-purple-200 bg-purple-50 text-purple-700 dark:border-purple-800 dark:bg-purple-900/40 dark:text-purple-300',
        },
    };

    return configs[urgency] || configs.scheduled;
};

const clearFilters = () => {
    filterState.search = '';
    urgencyFilter.value = '';
    retentionDurationFilter.value = '';
    rootFolderFilter.value = '';
    departmentFilter.value = '';
    expiresFromFilter.value = '';
    expiresToFilter.value = '';
    releasedFromFilter.value = '';
    releasedToFilter.value = '';

    // Clear all filter params and reload
    const currentUrl = new URL(window.location.href);
    const newUrl = currentUrl.pathname; // No query params

    router.visit(newUrl, {
        preserveState: true,
        preserveScroll: false,
        only: ['queue', 'filters', 'metrics'],
    });
};

const removeFilter = (key: keyof FilterForm) => {
    filterState[key] = '';
};

const selectedCount = computed(() => selection.value.size);

const allVisibleSelected = computed(() => {
    if (!filteredQueue.value.length) {
        return false;
    }
    return filteredQueue.value.every((record) =>
        selection.value.has(record.id),
    );
});

const toggleSelectAll = (checked: boolean) => {
    if (!checked) {
        selection.value = new Set();
        sessionStorage.removeItem(SELECTION_STORAGE_KEY);
        return;
    }

    const next = new Set(selection.value);
    filteredQueue.value.forEach((record) => next.add(record.id));
    selection.value = next;
};

const toggleRow = (id: string, checked: boolean) => {
    const next = new Set(selection.value);
    if (checked) {
        next.add(id);
    } else {
        next.delete(id);
    }
    selection.value = next;
};

const isSelected = (id: string) => {
    return selection.value.has(id);
};

const clearSelection = () => {
    selection.value = new Set();
    clearSelectionStorage();
};

const selectionPayload = () => ({
    file_ids: Array.from(selection.value),
});

const scheduleDisposal = () => {
    if (!selectedCount.value) return;
    resetScheduleErrors();
    scheduleDialogOpen.value = true;
};

const confirmScheduleDisposal = () => {
    if (!selectedCount.value) return;

    resetScheduleErrors();
    applyScheduleNoteSentenceCase();

    if (!scheduleForm.mode_of_disposal) {
        const message = 'Select how the records will be disposed.';
        scheduleFieldErrors.mode_of_disposal = message;
        scheduleErrorMessages.value = [message];
        return;
    }

    isSubmitting.value = true;

    const payload: Record<string, unknown> = {
        file_ids: Array.from(selection.value),
    };

    payload.mode_of_disposal = scheduleForm.mode_of_disposal;

    // Send datetime as ISO 8601 format, default to 1 hour from now if empty
    if (scheduleForm.scheduled_at) {
        payload.scheduled_at = new Date(
            scheduleForm.scheduled_at,
        ).toISOString();
    } else {
        // Default to 1 hour from now
        const oneHourFromNow = new Date();
        oneHourFromNow.setHours(oneHourFromNow.getHours() + 1);
        payload.scheduled_at = oneHourFromNow.toISOString();
    }
    const trimmedNote = scheduleForm.note.trim();
    if (trimmedNote) {
        payload.note = trimmedNote;
    }

    router.post('/disposal-management/schedule', payload, {
        preserveScroll: true,
        onError: (errors) => {
            const normalizedErrors = errors as ValidationErrorBag;
            const aggregatedMessages = aggregateErrorMessages(normalizedErrors);
            const fallbackMessage =
                'Unable to schedule disposal. Please review the form and try again.';

            scheduleErrorMessages.value = aggregatedMessages.length
                ? aggregatedMessages
                : [fallbackMessage];
            scheduleFieldErrors.scheduled_at = firstErrorMessage(
                'scheduled_at',
                normalizedErrors.scheduled_at,
            );
            scheduleFieldErrors.mode_of_disposal = firstErrorMessage(
                'mode_of_disposal',
                normalizedErrors.mode_of_disposal,
            );
            scheduleFieldErrors.note = firstErrorMessage(
                'note',
                normalizedErrors.note,
            );

            if (!aggregatedMessages.length) {
                error(
                    'Unable to schedule disposal',
                    'Please refresh and try again.',
                );
            }
        },
        onSuccess: () => {
            success('Batch scheduled', 'Records moved into audit review.');
            selection.value = new Set();
            sessionStorage.removeItem(SELECTION_STORAGE_KEY);
            resetScheduleErrors();
            scheduleDialogOpen.value = false;
            scheduleForm.scheduled_at = '';
            scheduleForm.note = '';
            scheduleForm.mode_of_disposal = '';
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const completeDisposal = () => {
    if (!selectedCount.value) return;
    completeDialogOpen.value = true;
};

const confirmCompleteDisposal = () => {
    if (!selectedCount.value) return;
    isSubmitting.value = true;

    const payload: any = {
        file_ids: Array.from(selection.value),
    };
    // Send datetime as ISO 8601 format
    if (completeForm.completed_at) {
        payload.completed_at = completeForm.completed_at;
    }

    router.post('/disposal-management/complete', payload, {
        preserveScroll: true,
        onError: () => {
            error(
                'Unable to complete disposal',
                'Confirm the records are still eligible.',
            );
        },
        onSuccess: () => {
            success('Disposal completed', 'Records transitioned to disposed.');
            selection.value = new Set();
            sessionStorage.removeItem(SELECTION_STORAGE_KEY);
            completeDialogOpen.value = false;
            completeForm.completed_at = '';
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const refreshQueue = () => {
    router.reload({ only: ['queue', 'metrics'] });
};

const selectedFileMetadata = computed(() => {
    if (!selectedFileId.value) return [];

    const record = filteredQueue.value.find(
        (r) => r.id === selectedFileId.value,
    );
    if (!record) return [];

    return [
        { label: 'Record ID', value: record.record_id },
        { label: 'File Name', value: record.file_name },
        { label: 'Author', value: record.author },
        {
            label: 'Date Created',
            value: record.date_created
                ? formatDate(record.date_created)
                : 'N/A',
        },
        {
            label: 'Date Released',
            value: record.date_released
                ? formatDate(record.date_released)
                : 'N/A',
        },
        { label: 'Division', value: record.department?.name || 'N/A' },
        { label: 'Folder', value: formattedTrail(record.folder.trail) },
        {
            label: 'Physical Location',
            value: formatPhysicalLocation(record.physical.tags),
        },
        {
            label: 'Retention Duration',
            value: retentionLabel(record.retention.duration),
        },
        {
            label: 'Retention Expires',
            value: record.retention.expires_at
                ? formatDate(record.retention.expires_at)
                : 'N/A',
        },
        { label: 'Retention Status', value: record.retention_status },
        {
            label: 'File Size',
            value: record.electronic.current_version.size
                ? formatFileSize(record.electronic.current_version.size)
                : 'N/A',
        },
        {
            label: 'MIME Type',
            value: record.electronic.current_version.mime_type || 'Unknown',
        },
        {
            label: 'Version',
            value: `${record.electronic.latest_version ?? '1'} of ${record.electronic.version_count}`,
        },
    ].map(({ label, value }) => ({
        label,
        value: value || 'N/A',
    }));
});

const selectedRecord = computed(
    () =>
        filteredQueue.value.find((r) => r.id === selectedFileId.value) ?? null,
);

const openInfoSheet = (fileId: string, fileName: string) => {
    selectedFileId.value = fileId;
    selectedFileName.value = fileName;
    infoSheetOpen.value = true;
};

const openFileViewer = (record: DisposalRecord) => {
    const hasFile = Boolean(record.file_path);

    if (!hasFile) {
        error(
            'No file attached',
            'This record does not have an electronic copy.',
        );
        return;
    }

    router.visit(`/file/${record.id}/view`);
};

const paginationLinks = computed(() => props.queue.links ?? []);

const visitLink = (url: string | null) => {
    if (!url) {
        return;
    }

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const activeFilterChips = computed(() => {
    const chips: { key: keyof FilterForm; label: string; value: string }[] = [];

    if (filterState.search) {
        chips.push({
            key: 'search',
            label: 'Keyword',
            value: filterState.search,
        });
    }

    return chips;
});

const queueRangeLabel = computed(() => {
    if (props.queue.total === 0) {
        return 'Showing 0 results';
    }

    return `Showing ${props.queue.from ?? 1}–${props.queue.to ?? props.queue.data.length} of ${props.queue.total}`;
});

const metricsCards = computed(() => [
    {
        label: 'Disposal Queue',
        value: props.queue.total,
        description: 'Current Filter Results',
        icon: Archive,
        onClick: () => refreshQueue(),
    },
    {
        label: 'Active Records',
        value: props.metrics.totals.active,
        description: 'Within Retention',
        icon: Layers,
        onClick: () => router.visit('/records'),
    },
    {
        label: 'Disposed',
        value: props.metrics.totals.disposed,
        description: 'Processed and retained as metadata',
        icon: ClipboardList,
        onClick: () => router.visit('/records'),
    },
    {
        label: 'Scheduled Batches',
        value: props.metrics.totals.scheduled_batches,
        description: 'Awaiting Completion',
        icon: CheckCircle2,
        onClick: () => router.visit('/disposal-batches?status=scheduled'),
    },
]);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Disposal Management" />

        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Metrics -->
            <div class="grid gap-4 md:grid-cols-4">
                <button
                    v-for="card in metricsCards"
                    :key="card.label"
                    class="cursor-pointer rounded-lg border bg-card p-4 text-left transition-all hover:border-primary hover:shadow-md"
                    @click="card.onClick"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-muted-foreground">
                                {{ card.label }}
                            </p>
                            <p class="mt-1 text-2xl font-bold">
                                {{ card.value.toLocaleString() }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ card.description }}
                            </p>
                        </div>
                        <component
                            :is="card.icon"
                            class="h-8 w-8 text-muted-foreground"
                        />
                    </div>
                </button>
            </div>

            <!-- Search & Filters -->
            <div
                class="flex flex-col gap-4 md:flex-row md:items-start md:gap-4"
            >
                <div class="flex-1">
                    <DisposalFilterComponent
                        :search-query="filterState.search"
                        :search-placeholder="'Search by Record ID, Author, Folder, or Department...'"
                        :show-filters="showFilters"
                        :retention-duration-filter="retentionDurationFilter"
                        :root-folder-filter="rootFolderFilter"
                        :department-filter="departmentFilter"
                        :expires-from-filter="expiresFromFilter"
                        :expires-to-filter="expiresToFilter"
                        :released-from-filter="releasedFromFilter"
                        :released-to-filter="releasedToFilter"
                        :retention-durations="options.retention_durations"
                        :root-folders="options.root_folders"
                        :departments="options.departments"
                        @update:search-query="filterState.search = $event"
                        @update:show-filters="showFilters = $event"
                        @update:retention-duration-filter="
                            retentionDurationFilter = $event
                        "
                        @update:root-folder-filter="rootFolderFilter = $event"
                        @update:department-filter="departmentFilter = $event"
                        @update:expires-from-filter="expiresFromFilter = $event"
                        @update:expires-to-filter="expiresToFilter = $event"
                        @update:released-from-filter="
                            releasedFromFilter = $event
                        "
                        @update:released-to-filter="releasedToFilter = $event"
                        @clear-filters="clearFilters"
                    />
                </div>

                <div
                    class="flex w-full flex-wrap items-center gap-3 md:w-auto md:flex-nowrap"
                >
                    <div class="flex items-center gap-2">
                        <Label class="text-sm text-muted-foreground"
                            >Urgency</Label
                        >
                        <Select
                            :model-value="urgencyFilter || 'all'"
                            @update:model-value="
                                (val) =>
                                    (urgencyFilter =
                                        val === 'all' || !val
                                            ? ''
                                            : String(val))
                            "
                        >
                            <SelectTrigger class="w-36 sm:w-40">
                                <SelectValue placeholder="All" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All</SelectItem>
                                <SelectItem value="immediate"
                                    >Immediate</SelectItem
                                >
                                <SelectItem value="soon">Soon</SelectItem>
                                <SelectItem value="scheduled"
                                    >Scheduled</SelectItem
                                >
                                <SelectItem value="perpetual"
                                    >Perpetual</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="flex items-center gap-2">
                        <Label class="text-sm text-muted-foreground"
                            >Show</Label
                        >
                        <Select v-model="perPage">
                            <SelectTrigger class="w-20">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in props.options.per_page"
                                    :key="option"
                                    :value="String(option)"
                                >
                                    {{ option }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </div>

            <!-- Selection Actions -->
            <div
                v-if="selectedCount"
                class="rounded-lg border-2 border-primary bg-primary/10 p-4 shadow-lg"
            >
                <div
                    class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
                >
                    <div class="space-y-2">
                        <p class="text-lg font-bold text-foreground">
                            {{ selectedCount }} Record{{
                                selectedCount === 1 ? '' : 's'
                            }}
                            Selected
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Choose an action to perform on the selected records
                        </p>
                        <div
                            v-if="selectionPreview.length"
                            class="flex flex-wrap gap-2 text-xs text-muted-foreground"
                        >
                            <Badge variant="outline" class="text-[10px]">
                                {{ selectionPreview.length }} on this page
                            </Badge>
                        </div>
                        <p
                            v-if="hasOffPageSelections"
                            class="text-xs text-muted-foreground"
                        >
                            {{ offPageSelectionCount }} selection{{
                                offPageSelectionCount === 1 ? '' : 's'
                            }}
                            on other pages.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            size="lg"
                            variant="outline"
                            class="cursor-pointer"
                            :disabled="isSubmitting"
                            @click="clearSelection"
                        >
                            <X class="mr-2 h-4 w-4" />
                            Clear Selection
                        </Button>
                        <Button
                            size="lg"
                            variant="outline"
                            class="cursor-pointer"
                            :disabled="isSubmitting"
                            @click="scheduleDisposal"
                        >
                            <Archive class="mr-2 h-4 w-4" />
                            Schedule Disposal
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Table - Desktop -->
            <div
                class="hidden w-full min-w-0 flex-1 flex-col overflow-hidden rounded-xl border bg-card shadow-sm md:flex"
            >
                <div
                    v-if="filteredQueue.length > 0"
                    class="flex min-h-0 w-full min-w-0 flex-1 flex-col overflow-hidden"
                >
                    <div class="flex-1 overflow-x-auto w-full">
                        <Table class="w-full min-w-[1080px] table-fixed">
                            <TableHeader
                                class="sticky top-0 z-10 bg-background [&_tr]:table [&_tr]:w-full [&_tr]:table-fixed"
                            >
                                <TableRow>
                                    <TableHead class="w-11 text-center">
                                        <Button
                                            variant="ghost"
                                            class="h-7 w-7 cursor-pointer rounded-full"
                                            :disabled="!filteredQueue.length"
                                            :aria-label="
                                                allVisibleSelected
                                                    ? 'Unselect current page'
                                                    : 'Select current page'
                                            "
                                            @click.stop="
                                                () =>
                                                    toggleSelectAll(
                                                        !allVisibleSelected,
                                                    )
                                            "
                                        >
                                            <CheckCircle2
                                                v-if="allVisibleSelected"
                                                class="h-4 w-4 text-primary"
                                            />
                                            <Circle
                                                v-else
                                                class="h-4 w-4 text-muted-foreground"
                                            />
                                        </Button>
                                    </TableHead>
                                    <TableHead
                                        class="w-96 cursor-pointer select-none hover:bg-muted/50"
                                        @click="() => handleSort('record_id')"
                                    >
                                        <div class="flex items-center gap-1">
                                            <span>Record</span>
                                            <component
                                                :is="getSortIcon('record_id')"
                                                class="h-4 w-4"
                                                :class="
                                                    sortBy === 'record_id'
                                                        ? 'text-primary'
                                                        : 'text-muted-foreground'
                                                "
                                            />
                                        </div>
                                    </TableHead>
                                    <TableHead class="w-36 text-left"
                                        >Status</TableHead
                                    >
                                    <TableHead class="w-64"
                                        >Physical Location</TableHead
                                    >
                                    <TableHead
                                        class="w-52 cursor-pointer text-left select-none hover:bg-muted/50"
                                        @click="
                                            () =>
                                                handleSort('retention_expires')
                                        "
                                    >
                                        <div
                                            class="flex items-center justify-start gap-1"
                                        >
                                            <span>Expired By</span>
                                            <component
                                                :is="
                                                    getSortIcon(
                                                        'retention_expires',
                                                    )
                                                "
                                                class="h-4 w-4"
                                                :class="
                                                    sortBy ===
                                                    'retention_expires'
                                                        ? 'text-primary'
                                                        : 'text-muted-foreground'
                                                "
                                            />
                                        </div>
                                    </TableHead>
                                    <TableHead class="w-44 text-left"
                                        >Retention Duration</TableHead
                                    >
                                    <TableHead class="w-23"></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody
                                class=" block max-h-[min(60vh,32rem)] overflow-y-auto [&_tr]:table [&_tr]:w-full [&_tr]:table-fixed"
                            >
                                <TableRow
                                    v-for="record in filteredQueue"
                                    :key="record.id"
                                    :class="[
                                        isSelected(record.id)
                                            ? 'border-l-4 border-primary/60 bg-primary/5 ring-1 ring-primary/40'
                                            : 'border-l-4 border-transparent',
                                    ]"
                                >
                                    <TableCell class="w-10 text-center">
                                        <Button
                                            variant="ghost"
                                            class="mx-auto h-7 w-7 cursor-pointer rounded-full"
                                            :aria-label="
                                                isSelected(record.id)
                                                    ? 'Deselect record'
                                                    : 'Select record'
                                            "
                                            @click.stop="
                                                () =>
                                                    toggleRow(
                                                        record.id,
                                                        !isSelected(record.id),
                                                    )
                                            "
                                        >
                                            <CheckCircle2
                                                v-if="isSelected(record.id)"
                                                class="h-4 w-4 text-primary"
                                            />
                                            <Circle
                                                v-else
                                                class="h-4 w-4 text-muted-foreground"
                                            />
                                        </Button>
                                    </TableCell>

                                    <TableCell class="w-96">
                                        <div class="space-y-1">
                                            <div
                                                class="flex items-center gap-1.5"
                                            >
                                                <TooltipProvider>
                                                    <Tooltip>
                                                        <TooltipTrigger
                                                            as-child
                                                        >
                                                            <span
                                                                class="inline-block max-w-[44ch] cursor-pointer truncate font-medium"
                                                                >{{
                                                                    record.file_name
                                                                }}</span
                                                            >
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            <p class="max-w-sm">
                                                                {{
                                                                    record.file_name
                                                                }}
                                                            </p>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                </TooltipProvider>
                                            </div>
                                            <TooltipProvider>
                                                <Tooltip>
                                                    <TooltipTrigger as-child>
                                                        <span
                                                            class="inline-block max-w-[18ch] cursor-pointer truncate text-xs text-muted-foreground"
                                                            >{{
                                                                record.record_id
                                                            }}</span
                                                        >
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        <p>
                                                            {{
                                                                record.record_id
                                                            }}
                                                        </p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                        </div>
                                    </TableCell>

                                    <TableCell class="w-36 text-left">
                                        <Badge
                                            variant="outline"
                                            :class="
                                                getUrgencyConfig(
                                                    record.urgency,
                                                ).class
                                            "
                                            class="text-xs"
                                        >
                                            {{
                                                getUrgencyConfig(
                                                    record.urgency,
                                                ).label
                                            }}
                                        </Badge>
                                    </TableCell>

                                    <TableCell class="w-64">
                                        <div class="space-y-1">
                                            <div
                                                v-if="
                                                    record.physical.tags.length
                                                "
                                                class="flex flex-wrap gap-1"
                                            >
                                                <template
                                                    v-if="
                                                        record.physical.tags
                                                            .length > 3
                                                    "
                                                >
                                                    <Badge
                                                        v-for="tag in record.physical.tags.slice(
                                                            0,
                                                            3,
                                                        )"
                                                        :key="tag.id"
                                                        variant="outline"
                                                        class="text-xs"
                                                    >
                                                        {{ tag.name }}
                                                    </Badge>
                                                    <TooltipProvider>
                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                as-child
                                                            >
                                                                <Badge
                                                                    variant="secondary"
                                                                    class="cursor-pointer text-xs"
                                                                >
                                                                    +{{
                                                                        record
                                                                            .physical
                                                                            .tags
                                                                            .length -
                                                                        3
                                                                    }}
                                                                    more
                                                                </Badge>
                                                            </TooltipTrigger>
                                                            <TooltipContent
                                                                class="bg-popover text-popover-foreground"
                                                            >
                                                                <div
                                                                    class="flex max-w-xs flex-wrap gap-1"
                                                                >
                                                                    <Badge
                                                                        v-for="tag in record
                                                                            .physical
                                                                            .tags"
                                                                        :key="
                                                                            tag.id
                                                                        "
                                                                        variant="outline"
                                                                        class="text-xs"
                                                                    >
                                                                        {{
                                                                            tag.name
                                                                        }}
                                                                    </Badge>
                                                                </div>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </TooltipProvider>
                                                </template>
                                                <template v-else>
                                                    <Badge
                                                        v-for="tag in record
                                                            .physical.tags"
                                                        :key="tag.id"
                                                        variant="outline"
                                                        class="text-xs"
                                                    >
                                                        {{ tag.name }}
                                                    </Badge>
                                                </template>
                                            </div>
                                        </div>
                                    </TableCell>

                                    <TableCell class="w-52 text-left">
                                        <p class="text-sm font-medium">
                                            {{
                                                formatDate(
                                                    record.retention
                                                        .expires_at ?? '',
                                                )
                                            }}
                                        </p>
                                    </TableCell>

                                    <TableCell class="w-44 text-left">
                                        <Badge
                                            variant="outline"
                                            class="text-xs"
                                            :class="
                                                retentionBadgeClass(
                                                    record.retention.duration,
                                                )
                                            "
                                        >
                                            {{
                                                retentionLabel(
                                                    record.retention.duration,
                                                )
                                            }}
                                        </Badge>
                                    </TableCell>

                                    <TableCell class="w-20">
                                        <div class="flex items-center gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="h-8 w-8"
                                                @click="openFileViewer(record)"
                                                title="Preview file"
                                            >
                                                <Eye class="h-4 w-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="h-8 w-8"
                                                @click="
                                                    openInfoSheet(
                                                        record.id,
                                                        record.file_name,
                                                    )
                                                "
                                                title="View details"
                                            >
                                                <Info class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>

                <!-- Empty state -->
                <div
                    v-else
                    class="flex flex-1 flex-col items-center justify-center py-20 text-center"
                >
                    <Archive class="mb-2 h-8 w-8 text-muted-foreground" />
                    <p class="font-medium">No records found</p>
                    <p class="text-sm text-muted-foreground">
                        No records match the current filters.
                    </p>
                    <Button
                        v-if="hasActiveFilters"
                        size="sm"
                        variant="outline"
                        class="mt-2"
                        @click="clearFilters"
                    >
                        Clear filters
                    </Button>
                </div>
            </div>

            <!-- Mobile Cards -->
            <div
                v-if="filteredQueue.length > 0"
                class="flex flex-col gap-3 md:hidden"
            >
                <div
                    v-for="record in filteredQueue"
                    :key="record.id"
                    class="rounded-lg border bg-card p-4 shadow-sm transition-colors"
                    :class="
                        isSelected(record.id)
                            ? 'border-primary bg-primary/5'
                            : 'hover:bg-muted/50'
                    "
                >
                    <div class="mb-3 flex items-start justify-between gap-2">
                        <div class="flex min-w-0 flex-1 items-start gap-2">
                            <Button
                                size="icon"
                                variant="ghost"
                                class="h-7 w-7 shrink-0 cursor-pointer rounded-full"
                                :aria-label="
                                    isSelected(record.id)
                                        ? 'Deselect record'
                                        : 'Select record'
                                "
                                @click.stop="
                                    () =>
                                        toggleRow(
                                            record.id,
                                            !isSelected(record.id),
                                        )
                                "
                            >
                                <CheckCircle2
                                    v-if="isSelected(record.id)"
                                    class="h-4 w-4 text-primary"
                                />
                                <Circle
                                    v-else
                                    class="h-4 w-4 text-muted-foreground"
                                />
                            </Button>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">
                                    {{ record.file_name }}
                                </p>
                                <p
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {{ record.record_id }}
                                </p>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            <Badge
                                variant="outline"
                                :class="getUrgencyConfig(record.urgency).class"
                                class="text-xs"
                            >
                                {{ getUrgencyConfig(record.urgency).label }}
                            </Badge>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-7 w-7"
                                @click="
                                    openInfoSheet(record.id, record.file_name)
                                "
                                title="View details"
                            >
                                <Info class="h-4 w-4 text-muted-foreground" />
                            </Button>
                        </div>
                    </div>

                    <div class="space-y-1 text-xs text-muted-foreground">
                        <div class="flex items-center justify-between">
                            <span>Retention Duration:</span>
                            <div class="flex items-center gap-1">
                                <Badge
                                    variant="outline"
                                    class="text-xs"
                                    :class="
                                        retentionBadgeClass(
                                            record.retention.duration,
                                        )
                                    "
                                >
                                    {{
                                        retentionLabel(
                                            record.retention.duration,
                                        )
                                    }}
                                </Badge>
                            </div>
                        </div>
                        <div class="flex justify-between">
                            <span>Expired By:</span>
                            <span class="font-medium text-foreground">{{
                                formatDate(record.retention.expires_at ?? '')
                            }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>File:</span>
                            <span class="font-medium text-foreground">
                                {{
                                    !record.file_path ||
                                    !record.electronic.current_version.size
                                        ? 'Physical Only'
                                        : formatFileSize(
                                              record.electronic.current_version
                                                  .size,
                                          )
                                }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-3 flex justify-end gap-1">
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-8 cursor-pointer gap-1 text-xs"
                            @click="openFileViewer(record)"
                        >
                            <Eye class="h-3.5 w-3.5" />
                            Preview
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Mobile empty state -->
            <div
                v-else
                class="flex flex-col items-center justify-center py-20 text-center md:hidden"
            >
                <Archive class="mb-4 h-16 w-16 text-muted-foreground/50" />
                <h2 class="mb-2 text-lg font-semibold">No Records Found</h2>
                <p class="max-w-md text-sm text-muted-foreground">
                    {{
                        hasActiveFilters
                            ? 'Try adjusting your filters to see more results.'
                            : 'There are no records in the disposal queue.'
                    }}
                </p>
                <Button
                    v-if="hasActiveFilters"
                    size="sm"
                    variant="outline"
                    class="mt-3"
                    @click="clearFilters"
                >
                    Clear filters
                </Button>
            </div>

            <!-- Pagination -->
            <div
                v-if="paginationLinks.length && filteredQueue.length > 0"
                class="flex flex-wrap items-center gap-1.5"
            >
                <Button
                    v-for="(link, index) in paginationLinks"
                    :key="`${link.label}-${index}`"
                    :disabled="!link.url"
                    variant="outline"
                    size="sm"
                    class="h-8 min-w-8 cursor-pointer px-2 text-xs"
                    :class="{
                        'bg-primary text-primary-foreground hover:bg-primary hover:text-primary-foreground dark:bg-white dark:text-black dark:hover:bg-white dark:hover:text-black':
                            link.active,
                    }"
                    @click="visitLink(link.url)"
                >
                    <span v-html="link.label" />
                </Button>
            </div>
        </div>

        <!-- Schedule Disposal Dialog -->
        <Dialog v-model:open="scheduleDialogOpen">
            <DialogContent class="sm:max-w-125">
                <DialogHeader>
                    <DialogTitle>Schedule Disposal</DialogTitle>
                    <DialogDescription>
                        Schedule {{ selectedCount }} record{{
                            selectedCount === 1 ? '' : 's'
                        }}
                        for disposal. Optionally set a specific scheduled date
                        (defaults to 1 hour from now).
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <AlertError
                        v-if="scheduleErrorMessages.length"
                        :errors="scheduleErrorMessages"
                        title="Unable to schedule disposal"
                    />

                    <div class="space-y-2">
                        <Label for="scheduled-at"
                            >Scheduled Date & Time (Optional)</Label
                        >
                        <Input
                            id="scheduled-at"
                            v-model="scheduleForm.scheduled_at"
                            type="datetime-local"
                            step="60"
                            :min="minScheduledDate"
                        />
                        <InputError
                            :message="scheduleFieldErrors.scheduled_at"
                        />
                        <p class="text-xs text-muted-foreground">
                            Pick a date and time at least one hour in the
                            future. Leave blank to auto-schedule for one hour
                            from now.
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="mode-of-disposal">Mode of Disposal</Label>
                        <Select v-model="scheduleForm.mode_of_disposal">
                            <SelectTrigger id="mode-of-disposal">
                                <SelectValue placeholder="Select a mode" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem
                                        v-for="mode in disposalModeOptions"
                                        :key="mode.value"
                                        :value="mode.value"
                                    >
                                        {{ mode.label }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <InputError
                            :message="scheduleFieldErrors.mode_of_disposal"
                        />
                        <p class="text-xs text-muted-foreground">
                            Required — choose how these records will be
                            disposed.
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="schedule-note">Note (Optional)</Label>
                        <Textarea
                            id="schedule-note"
                            v-model="scheduleForm.note"
                            @blur="applyScheduleNoteSentenceCase"
                            @keyup.enter="applyScheduleNoteSentenceCase"
                            placeholder="Add context for auditors or downstream reviewers..."
                            rows="3"
                            maxlength="1000"
                        />
                        <InputError :message="scheduleFieldErrors.note" />
                        <p class="text-xs text-muted-foreground">
                            {{ scheduleForm.note.length }}/1000 characters ·
                            Share context if helpful for reviewers.
                        </p>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="scheduleDialogOpen = false"
                        :disabled="isSubmitting"
                        class="cursor-pointer hover:bg-gray-800 hover:bg-primary/10"
                    >
                        Cancel
                    </Button>
                    <Button
                        @click="confirmScheduleDisposal"
                        :disabled="
                            isSubmitting || !scheduleForm.mode_of_disposal
                        "
                        class="cursor-pointer hover:bg-gray-100 hover:bg-primary/80"
                    >
                        <Archive class="mr-2 h-4 w-4" />
                        Schedule Disposal
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Complete Disposal Dialog -->
        <Dialog v-model:open="completeDialogOpen">
            <DialogContent class="sm:max-w-125">
                <DialogHeader>
                    <DialogTitle>Complete Disposal</DialogTitle>
                    <DialogDescription>
                        Mark {{ selectedCount }} record{{
                            selectedCount === 1 ? '' : 's'
                        }}
                        as disposed. This action will clear physical locations.
                        Optionally set a specific completion date (defaults to
                        now).
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label for="completed-at"
                            >Completion Date & Time (Optional)</Label
                        >
                        <Input
                            id="completed-at"
                            v-model="completeForm.completed_at"
                            type="datetime-local"
                            step="60"
                        />
                        <p class="text-xs text-muted-foreground">
                            Leave empty to use current date and time (Manila
                            timezone, UTC+8).
                        </p>
                    </div>

                    <div
                        class="rounded-lg border border-orange-200 bg-orange-50 p-3 dark:border-orange-800 dark:bg-orange-900/20"
                    >
                        <div class="flex items-start gap-2">
                            <AlertTriangle
                                class="mt-0.5 h-5 w-5 shrink-0 text-orange-600 dark:text-orange-400"
                            />
                            <div class="space-y-1">
                                <p
                                    class="text-sm font-medium text-orange-800 dark:text-orange-200"
                                >
                                    Warning: This action is irreversible
                                </p>
                                <p
                                    class="text-xs text-orange-700 dark:text-orange-300"
                                >
                                    Physical locations will be cleared and
                                    records will be marked as disposed.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="completeDialogOpen = false"
                        :disabled="isSubmitting"
                    >
                        Cancel
                    </Button>
                    <Button
                        @click="confirmCompleteDisposal"
                        :disabled="isSubmitting"
                        variant="destructive"
                    >
                        <CheckCircle2 class="mr-2 h-4 w-4" />
                        Complete Disposal
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- File Info Sheet -->
        <ItemMetadataSheet
            v-model:open="infoSheetOpen"
            :item-name="selectedFileName"
            :has-metadata="selectedFileMetadata.length > 0"
            :metadata-entries="selectedFileMetadata"
            :file-id="selectedFileId"
            :file-name="selectedFileName"
            :has-file="true"
            :uploaded-at="selectedRecord?.created_at ?? null"
            :modified-at="selectedRecord?.updated_at ?? null"
            :disable-version-actions="true"
        />
    </AppLayout>
</template>
