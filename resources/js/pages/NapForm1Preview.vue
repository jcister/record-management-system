<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { ArrowLeft, ChevronsUpDown, Download, Eye, EyeOff, FileText, Search } from 'lucide-vue-next';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Combobox,
    ComboboxAnchor,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxTrigger,
} from '@/components/ui/combobox';
import AppLayout from '@/layouts/AppLayout.vue';
import { useToast } from '@/composables/useToast';
import type { BreadcrumbItem } from '@/types';
import { napForm1Generate, show as showBatch } from '@/actions/App/Http/Controllers/DisposalBatchController';
import PdfPreviewPane from '@/components/common/PdfPreviewPane.vue';

interface Item {
    title: string;
    period: string;
    volume: string;
    location: string;
    frequency: string;
    duplication: string;
    time_value: string;
    utility_value: string;
    retention_active: string;
    retention_storage: string;
    retention_total: string;
    provision: string;
    medium?: string;
    restriction?: string;
}

interface Props {
    batch: {
        id: string;
        reference: string;
        status: string;
        file_count: number;
        nap_form_1_generated_at: string | null;
        nap_form_1_file_exists: boolean;
    };
    defaultData: {
        agency: string;
        organizational_unit: string;
        section_unit?: string;
        email?: string;
        telephone: string;
        address: string;
        person_in_charge: string;
        date_prepared: string;
        prepared_by_user_id?: string | null;
        prepared_by_name: string;
        prepared_by_position: string;
        prepared_by_role: string;
        assisted_by_name: string;
        assisted_by_designation: string;
        assisted_by_role: string;
        approved_by_name: string;
        approved_by_designation: string;
        approved_by_role: string;
        volume_in_cubic_meter: string;
        location_of_records: string;
        frequency_of_use: string;
        time_value: string;
        utility_value: string;
        retention_period: string;
        disposition_provision: string;
        items: Item[];
    };
    users: Array<{
        id: string;
        name: string;
        email: string;
        position: string | null;
        role: string | null;
    }>;
    currentUser: {
        id: string;
        name: string;
        email: string;
        position: string | null;
        role: string | null;
    };
}

const props = defineProps<Props>();
const { success, error } = useToast();

const signatoryPrefixes = new Set(['mr', 'mr.', 'mrs', 'mrs.', 'ms', 'ms.', 'dr', 'dr.', 'engr', 'engr.', 'atty', 'atty.', 'prof', 'prof.', 'hon', 'hon.']);

const toMiddleInitial = (token: string): string => {
    const trimmed = token.trim();

    if (trimmed === '') {
        return '';
    }

    return `${trimmed.charAt(0).toUpperCase()}.`;
};

const formatSignatoryName = (rawValue: string | null | undefined): string => {
    const trimmedValue = (rawValue ?? '').trim();

    if (trimmedValue === '') {
        return '';
    }

    const [mainNameRaw, extensionRaw = ''] = trimmedValue.split(',', 2);
    const mainName = mainNameRaw.trim();
    const extension = extensionRaw.trim();
    const tokens = mainName.split(/\s+/).filter(Boolean);

    if (tokens.length === 0) {
        return extension !== '' ? `, ${extension}` : '';
    }

    const hasPrefix = signatoryPrefixes.has(tokens[0].toLowerCase());

    let prefix = '';
    const nameTokens = [...tokens];

    if (hasPrefix) {
        prefix = nameTokens.shift() || '';
    }

    let firstName = '';
    let middleName = '';
    let lastName = '';

    if (nameTokens.length >= 3) {
        lastName = nameTokens[nameTokens.length - 1];
        middleName = nameTokens[nameTokens.length - 2];
        firstName = nameTokens.slice(0, -2).join(' ');
    } else if (nameTokens.length === 2) {
        [firstName, lastName] = nameTokens;
    } else {
        firstName = nameTokens[0] || '';
    }

    const middleInitial = toMiddleInitial(middleName);

    const coreParts = [
        prefix ? prefix.toUpperCase() : '',
        firstName ? firstName.toUpperCase() : '',
        middleInitial,
        lastName ? lastName.toUpperCase() : '',
    ].filter((value) => value !== '');

    const coreName = coreParts.join(' ').replace(/\s+/g, ' ').trim();

    return extension !== '' ? `${coreName}, ${extension.toUpperCase()}` : coreName;
};

const triggerFileDownload = async (url: string, fileName: string) => {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }

    const response = await fetch(url, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) {
        throw new Error(`Download failed with status ${response.status}`);
    }

    const blob = await response.blob();
    const objectUrl = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = objectUrl;
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(objectUrl);
};

const wait = (milliseconds: number) => new Promise((resolve) => setTimeout(resolve, milliseconds));

const downloadWithRetry = async (url: string, fileName: string) => {
    const maxAttempts = 8;

    for (let attempt = 1; attempt <= maxAttempts; attempt += 1) {
        try {
            await triggerFileDownload(url, fileName);
            return;
        } catch (downloadError: any) {
            const message = String(downloadError?.message || '');
            const isNotFound = message.includes('status 404');

            if (!isNotFound || attempt === maxAttempts) {
                throw downloadError;
            }

            await wait(400 * attempt);
        }
    }
};

const form = useForm({
    agency: props.defaultData.agency,
    organizational_unit: props.defaultData.organizational_unit,
    section_unit: props.defaultData.section_unit || '',
    email: props.defaultData.email || '',
    telephone: props.defaultData.telephone,
    address: props.defaultData.address,
    person_in_charge: props.defaultData.person_in_charge,
    date_prepared: props.defaultData.date_prepared,
    prepared_by_user_id: (props.defaultData.prepared_by_user_id || props.currentUser.id) as string | null,
    prepared_by_name: formatSignatoryName(props.defaultData.prepared_by_name || props.currentUser.name),
    prepared_by_position: props.defaultData.prepared_by_position || props.currentUser.position || '',
    prepared_by_role: props.defaultData.prepared_by_role || props.currentUser.role || '',
    assisted_by_name: props.defaultData.assisted_by_name,
    assisted_by_designation: props.defaultData.assisted_by_designation,
    assisted_by_role: props.defaultData.assisted_by_role,
    approved_by_name: formatSignatoryName(props.defaultData.approved_by_name),
    approved_by_designation: props.defaultData.approved_by_designation,
    approved_by_role: props.defaultData.approved_by_role,
    approved_by_user_id: null as string | null,
    items: props.defaultData.items.map((item) => ({
        ...item,
        volume: item.volume || props.defaultData.volume_in_cubic_meter || '',
        location: item.location || props.defaultData.location_of_records || '',
        frequency: item.frequency || props.defaultData.frequency_of_use || '',
        time_value: item.time_value || props.defaultData.time_value || '',
        utility_value: item.utility_value || props.defaultData.utility_value || '',
        retention_active: item.retention_active || props.defaultData.retention_period || '',
        retention_storage: item.retention_storage || '',
        retention_total: item.retention_total || '',
        provision: item.provision || props.defaultData.disposition_provision || '',
        medium: item.medium || '',
        restriction: item.restriction || '',
    })),
});

const syncPersonInCharge = (value: string | null | undefined): void => {
    const normalized = typeof value === 'string' ? value.trim() : '';
    form.person_in_charge = normalized;
};

watch(
    () => form.prepared_by_position,
    (preparedPosition) => {
        syncPersonInCharge(preparedPosition);
    },
    { immediate: true }
);

const isDownloading = ref(false);
const showPreview = ref(typeof window !== 'undefined' && window.innerWidth >= 1024);
const isPreviewSyncing = ref(false);
const previewErrorMessage = ref<string | null>(null);
const hasGeneratedPreview = ref(Boolean(props.batch.nap_form_1_generated_at && props.batch.nap_form_1_file_exists));
const previewVersion = ref(Date.now());
const previewPdfPath = ref<string | null>(null);
const finalizeDownloadDialogOpen = ref(false);

const hasPreviewDocument = computed(() => hasGeneratedPreview.value || Boolean(previewPdfPath.value));

const inlinePdfUrl = computed(() => {
    if (previewPdfPath.value && typeof window !== 'undefined') {
        const query = new URLSearchParams({
            disposition: 'inline',
            preview_path: previewPdfPath.value,
            t: previewVersion.value.toString(),
        });

        return new URL(`/disposal-batches/${props.batch.id}/nap-form-1/download?${query.toString()}`, window.location.origin).toString() + '#navpanes=0&view=FitH';
    }

    if (!hasGeneratedPreview.value || typeof window === 'undefined') {
        return null;
    }

    const query = new URLSearchParams({
        disposition: 'inline',
        t: previewVersion.value.toString(),
    });

    return new URL(`/disposal-batches/${props.batch.id}/nap-form-1/download?${query.toString()}`, window.location.origin).toString() + '#navpanes=0&view=FitH';
});

const timeValueOptions = [
    { value: 'T', label: 'T - Temporary' },
    { value: 'P', label: 'P - Permanent' },
];

const utilityValueOptions = [
    { value: 'Adm', label: 'Adm - Administrative' },
    { value: 'F', label: 'F - Fiscal' },
    { value: 'L', label: 'L - Legal' },
    { value: 'Arc', label: 'Arc - Archival' },
];

const handleItemVolumeInput = (index: number, event: Event): void => {
    const target = event.target as HTMLInputElement;
    const rawValue = target.value;

    form.items[index].volume = rawValue ? Math.floor(Number(rawValue)).toString() : '';
};

const requiredFields = computed(() => {
    return [
        { key: 'organizational_unit', label: 'Organizational Unit', value: form.organizational_unit },
        { key: 'prepared_by_position', label: 'Prepared By Position', value: form.prepared_by_position },
        { key: 'prepared_by_role', label: 'Prepared By Role', value: form.prepared_by_role },
    ];
});

const missingFields = computed(() => requiredFields.value.filter((field) => !String(field.value || '').trim()));
const missingFieldKeys = computed(() => new Set(missingFields.value.map((field) => field.key)));
const hasFieldError = (key: string) => missingFieldKeys.value.has(key);
const isFormValid = computed(() => missingFields.value.length === 0);
const availableApprovedByUsers = computed(() => {
    return props.users.filter((entry) => entry.id !== form.prepared_by_user_id);
});
const availablePreparedByUsers = computed(() => {
    return props.users.filter((entry) => entry.id !== form.approved_by_user_id);
});

const preparedBySearch = ref(formatSignatoryName(props.defaultData.prepared_by_name || props.currentUser.name || ''));
const approvedBySearch = ref(formatSignatoryName(props.defaultData.approved_by_name || ''));
const approvedByUseDropdown = ref(true);
const preparedByUseDropdown = ref(true);

const normalizeApprovedManualName = (): void => {
    form.approved_by_name = formatSignatoryName(form.approved_by_name || '');
};

const normalizePreparedManualName = (): void => {
    form.prepared_by_name = formatSignatoryName(form.prepared_by_name || '');
};

const filterUsers = (list: string[], term: string) => {
    return list.filter((val) => val.toLowerCase().includes(term.toLowerCase()));
};

const handleApprovedByUserChange = (value: any) => {
    if (!value) {
        return;
    }

    const userName = String(value);

    const user = props.users.find((entry) => entry.name === userName);
    if (!user) {
        return;
    }

    if (user.id === form.prepared_by_user_id) {
        error('Selection Not Allowed', 'Approved By and Prepared By must be different users.');
        return;
    }

    form.approved_by_user_id = user.id;
    form.approved_by_name = formatSignatoryName(user.name);
    form.approved_by_designation = user.position || '';
    form.approved_by_role = user.role || '';
};

const toggleApprovedByMode = () => {
    approvedByUseDropdown.value = !approvedByUseDropdown.value;
    if (approvedByUseDropdown.value) {
        approvedBySearch.value = form.approved_by_name || '';
    } else {
        form.approved_by_user_id = null;
    }
};

const togglePreparedByMode = () => {
    preparedByUseDropdown.value = !preparedByUseDropdown.value;
    if (preparedByUseDropdown.value) {
        preparedBySearch.value = form.prepared_by_name || '';
        return;
    }
};

const handleUserChange = (value: any) => {
    if (!value) {
        return;
    }

    const userName = String(value);

    const user = props.users.find((entry) => entry.name === userName);
    if (!user) {
        return;
    }

    if (user.id === form.approved_by_user_id) {
        error('Selection Not Allowed', 'Prepared By and Approved By must be different users.');
        return;
    }

    form.prepared_by_user_id = user.id;
    form.prepared_by_name = formatSignatoryName(user.name);
    form.prepared_by_position = user.position || '';
    form.prepared_by_role = user.role || '';
};

const togglePreview = () => {
    showPreview.value = !showPreview.value;
};

const goBack = () => {
    router.visit(showBatch.url(props.batch.id));
};

const generatePdfForPreview = async (mode: 'preview' | 'finalize'): Promise<{ preview_path?: string }> => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const payload = {
        ...form.data(),
        prepared_by_user_id: form.prepared_by_user_id || props.currentUser.id,
        person_in_charge: (form.prepared_by_position || '').trim(),
        preview_sync: mode === 'preview',
    };

    const response = await fetch(napForm1Generate.url(props.batch.id), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify(payload),
    });

    if (response.ok) {
        return await response.json();
    }

    let message = `Generation failed with status ${response.status}`;
    try {
        const payload = await response.json();
        if (payload?.message) {
            message = String(payload.message);
        } else if (payload?.errors && typeof payload.errors === 'object') {
            const joined = Object.values(payload.errors).flat().join(' ');
            if (joined) {
                message = joined;
            }
        }
    } catch {
        // Use fallback message when response is not JSON.
    }

    throw new Error(message);
};

const openFinalizeDownloadDialog = () => {
    if (!isFormValid.value) {
        return;
    }

    finalizeDownloadDialogOpen.value = true;
};

const syncPdfPreview = () => {
    if (!isFormValid.value) {
        previewErrorMessage.value = `Please complete: ${missingFields.value.map((field) => field.label).join(', ')}`;
        return;
    }

    previewErrorMessage.value = null;
    isPreviewSyncing.value = true;

    generatePdfForPreview('preview')
        .then((payload) => {
            if (payload?.preview_path) {
                previewPdfPath.value = String(payload.preview_path);
            }
            previewVersion.value = Date.now();
            success('Preview Synced', 'Live preview now shows the latest generated PDF output.');
        })
        .catch((syncError: any) => {
            previewErrorMessage.value = String(syncError?.message || 'Failed to sync preview.');
            error('Sync Failed', previewErrorMessage.value);
        })
        .finally(() => {
            isPreviewSyncing.value = false;
        });
};

const confirmFinalizeAndDownloadPdf = () => {
    if (!isFormValid.value) {
        return;
    }

    finalizeDownloadDialogOpen.value = false;
    isDownloading.value = true;

    generatePdfForPreview('finalize')
        .then(async () => {
            const query = new URLSearchParams({
                format: 'pdf',
                t: Date.now().toString(),
            });
            const url = new URL(`/disposal-batches/${props.batch.id}/nap-form-1/download?${query.toString()}`, window.location.origin).toString();

            await downloadWithRetry(url, `nap-form-1-${props.batch.reference}.pdf`);
            previewPdfPath.value = null;
            hasGeneratedPreview.value = true;
            previewVersion.value = Date.now();
            success('NAP Form 1 Ready', 'Final NAP Form 1 PDF has been generated and downloaded.');

            // Give the browser a brief moment to start the download before navigation.
            setTimeout(() => {
                allowNavigation.value = true;
                router.visit(showBatch.url(props.batch.id));
            }, 200);
        })
        .catch((finalizeError: any) => {
            error('Finalize Failed', String(finalizeError?.message || 'Failed to finalize NAP Form 1 PDF.'));
        })
        .finally(() => {
            isDownloading.value = false;
        });
};

const allowNavigation = ref(false);

const handleBeforeUnload = (e: BeforeUnloadEvent) => {
    if (form.isDirty && !allowNavigation.value) {
        e.preventDefault();
        e.returnValue = '';
        return '';
    }
};

let removeRouterListener: (() => void) | null = null;

onMounted(() => {
    window.addEventListener('beforeunload', handleBeforeUnload);
    removeRouterListener = router.on('before', (event) => {
        if (form.isDirty && !allowNavigation.value) {
            if (!confirm('You have unsaved changes. Are you sure you want to leave?')) {
                event.preventDefault();
            }
        }
    });
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload);
    if (removeRouterListener) {
        removeRouterListener();
    }
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Disposal Management', href: '/disposal-management' },
    { title: 'Disposal Batches', href: '/disposal-batches' },
    { title: props.batch.reference, href: showBatch.url(props.batch.id) },
    { title: 'NAP Form 1', href: '' },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`NAP Form 1 - ${batch.reference}`" />

        <div class="border-b bg-background px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Button variant="ghost" size="icon" @click="goBack">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                    <div>
                        <h1 class="text-2xl font-semibold text-foreground">NAP Form 1 - Records Inventory and Appraisal</h1>
                        <p class="text-sm text-muted-foreground">Complete required fields and review before generating</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Badge variant="outline" class="capitalize">{{ batch.reference }}</Badge>
                    <Button variant="outline" size="sm" @click="togglePreview" class="hidden lg:flex">
                        <Eye v-if="!showPreview" class="mr-2 h-4 w-4" />
                        <EyeOff v-else class="mr-2 h-4 w-4" />
                        {{ showPreview ? 'Hide' : 'Show' }} Preview
                    </Button>
                </div>
            </div>
        </div>

        <div class="flex h-[calc(100vh-10rem)] overflow-hidden">
            <!-- Form Panel: own scrollbar, constrained by min-w-0 -->
            <div class="min-w-0 overflow-y-auto overflow-x-hidden transition-all duration-300" :class="showPreview ? 'w-full lg:w-1/2' : 'w-full'">
                <div class="space-y-6 p-6">
                    <div class="rounded-lg border border-blue-200 bg-blue-50/50 p-4 dark:border-blue-800 dark:bg-blue-900/10">
                        <div class="flex items-start gap-3">
                            <FileText class="h-5 w-5 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" />
                            <div class="flex-1">
                                <p class="text-sm font-medium text-blue-900 dark:text-blue-100 mb-1">NAP Form No. 1 - Records Inventory and Appraisal</p>
                                <p class="text-xs text-blue-700 dark:text-blue-300">Some fields are prefilled from your batch and user profile. Complete all required fields before generating.</p>
                            </div>
                        </div>
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle>Required Information</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid gap-4 md:grid-cols-3">
                                <div class="space-y-2 md:col-span-3">
                                    <Label>2. Organizational Unit <span class="text-red-500">*</span></Label>
                                    <Input
                                        v-model="form.organizational_unit"
                                        :class="[
                                            'uppercase',
                                            hasFieldError('organizational_unit')
                                                ? 'border-destructive focus-visible:ring-destructive/40'
                                                : '',
                                        ]"
                                    />
                                    <p v-if="hasFieldError('organizational_unit')" class="text-xs text-destructive">
                                        Organizational Unit is required.
                                    </p>
                                </div>
                                <div class="space-y-2 md:col-span-2">
                                    <Label>3. Section / Unit</Label>
                                    <Input
                                        v-model="form.section_unit"
                                        placeholder="e.g., RECORDS UNIT"
                                        class="uppercase"
                                    />
                                </div>
                                <div class="space-y-2 md:col-span-1">
                                    <Label>5. Email Address</Label>
                                    <Input
                                        v-model="form.email"
                                        type="email"
                                        placeholder="e.g., recordsoffice@agency.gov.ph"
                                    />
                                </div>
                            </div>

                            <div class="space-y-3 border-t pt-4">
                                <div class="flex items-center justify-between">
                                    <Label class="text-sm font-medium">Approved By</Label>
                                    <Button variant="ghost" size="sm" class="text-xs h-7" @click="toggleApprovedByMode">
                                        {{ approvedByUseDropdown ? 'Type manually' : 'Choose from list' }}
                                    </Button>
                                </div>
                                <div class="grid gap-4 md:grid-cols-3 min-w-0">
                                    <div class="space-y-2 min-w-0">
                                        <Label>Name</Label>
                                        <template v-if="approvedByUseDropdown">
                                            <Combobox v-model="form.approved_by_name" :filter-function="filterUsers" @update:model-value="handleApprovedByUserChange">
                                                <ComboboxAnchor class="w-full min-w-0">
                                                    <ComboboxTrigger class="w-full min-w-0 cursor-pointer">
                                                        <span :class="form.approved_by_name ? 'text-foreground flex-1 truncate text-left' : 'text-muted-foreground flex-1 truncate text-left'">
                                                            {{ form.approved_by_name || 'Select user...' }}
                                                        </span>
                                                        <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                                    </ComboboxTrigger>
                                                </ComboboxAnchor>
                                                <ComboboxContent>
                                                    <div class="flex items-center border-b px-3">
                                                        <Search class="w-4 h-4 text-gray-500" />
                                                        <ComboboxInput
                                                            placeholder="Search user..."
                                                            class="flex h-9 w-full rounded-none border-0 bg-transparent py-3 text-sm shadow-none outline-none placeholder:text-muted-foreground focus-visible:ring-0"
                                                        />
                                                    </div>
                                                    <ComboboxEmpty>No users found.</ComboboxEmpty>
                                                    <ComboboxItem v-for="user in availableApprovedByUsers" :key="user.id" :value="user.name" class="cursor-pointer">
                                                        {{ user.name }}
                                                    </ComboboxItem>
                                                </ComboboxContent>
                                            </Combobox>
                                        </template>
                                        <template v-else>
                                            <Input v-model="form.approved_by_name" placeholder="Enter name" @blur="normalizeApprovedManualName" />
                                        </template>
                                    </div>
                                    <div class="space-y-2 min-w-0">
                                        <Label>Position</Label>
                                        <Input v-model="form.approved_by_designation" :readonly="approvedByUseDropdown" :disabled="approvedByUseDropdown" />
                                    </div>
                                    <div class="space-y-2 min-w-0">
                                        <Label>Role</Label>
                                        <Input v-model="form.approved_by_role" :readonly="approvedByUseDropdown" :disabled="approvedByUseDropdown" />
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-3 border-t pt-4">
                                <div class="flex items-center justify-between">
                                    <Label class="text-sm font-medium">Prepared By</Label>
                                    <Button variant="ghost" size="sm" class="text-xs h-7" @click="togglePreparedByMode">
                                        {{ preparedByUseDropdown ? 'Type manually' : 'Choose from list' }}
                                    </Button>
                                </div>
                                <div class="grid gap-4 md:grid-cols-3 min-w-0">
                                    <div class="space-y-2 min-w-0">
                                        <Label>Name</Label>
                                        <template v-if="preparedByUseDropdown">
                                            <Combobox v-model="form.prepared_by_name" :filter-function="filterUsers" @update:model-value="handleUserChange">
                                                <ComboboxAnchor class="w-full min-w-0">
                                                    <ComboboxTrigger class="w-full min-w-0 cursor-pointer">
                                                        <span :class="form.prepared_by_name ? 'text-foreground flex-1 truncate text-left' : 'text-muted-foreground flex-1 truncate text-left'">
                                                            {{ form.prepared_by_name || 'Select user...' }}
                                                        </span>
                                                        <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                                    </ComboboxTrigger>
                                                </ComboboxAnchor>
                                                <ComboboxContent>
                                                    <div class="flex items-center border-b px-3">
                                                        <Search class="w-4 h-4 text-gray-500" />
                                                        <ComboboxInput
                                                            placeholder="Search user..."
                                                            class="flex h-9 w-full rounded-none border-0 bg-transparent py-3 text-sm shadow-none outline-none placeholder:text-muted-foreground focus-visible:ring-0"
                                                        />
                                                    </div>
                                                    <ComboboxEmpty>No users found.</ComboboxEmpty>
                                                    <ComboboxItem v-for="user in availablePreparedByUsers" :key="user.id" :value="user.name" class="cursor-pointer">
                                                        {{ user.name }}
                                                    </ComboboxItem>
                                                </ComboboxContent>
                                            </Combobox>
                                        </template>
                                        <template v-else>
                                            <Input v-model="form.prepared_by_name" placeholder="Enter name" @blur="normalizePreparedManualName" />
                                        </template>
                                    </div>
                                    <div class="space-y-2 min-w-0">
                                        <Label>Position <span class="text-red-500">*</span></Label>
                                        <Input
                                            v-model="form.prepared_by_position"
                                            :readonly="preparedByUseDropdown"
                                            :disabled="preparedByUseDropdown"
                                            :class="[
                                                hasFieldError('prepared_by_position')
                                                    ? 'border-destructive focus-visible:ring-destructive/40'
                                                    : '',
                                            ]"
                                        />
                                        <p v-if="hasFieldError('prepared_by_position')" class="text-xs text-destructive">
                                            Prepared By Position is required.
                                        </p>
                                    </div>
                                    <div class="space-y-2 min-w-0">
                                        <Label>Role <span class="text-red-500">*</span></Label>
                                        <Input
                                            v-model="form.prepared_by_role"
                                            :readonly="preparedByUseDropdown"
                                            :disabled="preparedByUseDropdown"
                                            :class="[
                                                hasFieldError('prepared_by_role')
                                                    ? 'border-destructive focus-visible:ring-destructive/40'
                                                    : '',
                                            ]"
                                        />
                                        <p v-if="hasFieldError('prepared_by_role')" class="text-xs text-destructive">
                                            Prepared By Role is required.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-3 border-t pt-4">
                                <Label class="text-sm font-medium">Record Series Rows</Label>
                                <p class="text-xs text-muted-foreground">Record-row details are optional and can be filled later.</p>
                                <div class="space-y-3">
                                    <details v-for="(item, index) in form.items" :key="`row-card-${index}`" class="rounded-md border bg-card/40 overflow-hidden">
                                        <summary class="cursor-pointer list-none px-3 py-2">
                                            <div class="flex items-center justify-between gap-3 min-w-0">
                                                <div class="w-0 flex-1 overflow-hidden">
                                                    <p class="truncate text-sm font-medium">{{ item.title || `Row ${index + 1}` }}</p>
                                                    <p class="truncate text-xs text-muted-foreground">{{ item.period || 'No period yet' }}</p>
                                                </div>
                                                <Badge variant="outline" class="text-xs shrink-0">Edit details</Badge>
                                            </div>
                                        </summary>
                                        <div class="border-t px-3 pb-3 pt-3">
                                            <div class="grid gap-2 md:grid-cols-2 min-w-0">
                                                <div class="space-y-1 min-w-0 md:col-span-2">
                                                    <Label class="text-xs">9. Record Series Title</Label>
                                                    <Input v-model="item.title" class="h-8" readonly />
                                                </div>
                                                <div class="space-y-1 min-w-0">
                                                    <Label class="text-xs">10. Period Covered</Label>
                                                    <Input v-model="item.period" class="h-8" readonly />
                                                </div>
                                                <div class="space-y-1">
                                                    <Label class="text-xs">11. Volume</Label>
                                                    <div class="relative">
                                                        <Input
                                                            :model-value="item.volume"
                                                            type="number"
                                                            min="0"
                                                            step="1"
                                                            placeholder="e.g., 5"
                                                            class="h-8 pr-10"
                                                            @input="handleItemVolumeInput(index, $event)"
                                                        />
                                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-muted-foreground pointer-events-none">
                                                            m³
                                                        </span>
                                                    </div>
                                                    <p class="text-[11px] text-muted-foreground">Optional - Numbers only</p>
                                                </div>
                                                <div class="space-y-1">
                                                    <Label class="text-xs">12. Records Medium</Label>
                                                    <Input v-model="item.medium" class="h-8" placeholder="e.g., Paper / Digital" />
                                                </div>
                                                <div class="space-y-1">
                                                    <Label class="text-xs">13. Restriction/s</Label>
                                                    <Input v-model="item.restriction" class="h-8" placeholder="e.g., Restricted / None" />
                                                </div>
                                                <div class="space-y-1">
                                                    <Label class="text-xs">14. Location</Label>
                                                    <Input v-model="item.location" class="h-8" />
                                                </div>
                                                <div class="space-y-1">
                                                    <Label class="text-xs">15. Frequency</Label>
                                                    <Input v-model="item.frequency" class="h-8" />
                                                </div>
                                                <div class="space-y-1">
                                                    <Label class="text-xs">16. Duplication</Label>
                                                    <Input v-model="item.duplication" class="h-8" />
                                                </div>
                                                <div class="space-y-1">
                                                    <Label class="text-xs">17. Time Value (T/P)</Label>
                                                    <Select v-model="item.time_value">
                                                        <SelectTrigger class="h-8">
                                                            <SelectValue placeholder="Select" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem v-for="option in timeValueOptions" :key="`time-card-${option.value}`" :value="option.value">{{ option.label }}</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div class="space-y-1">
                                                    <Label class="text-xs">18. Utility Value (Adm/F/L/Arc)</Label>
                                                    <Select v-model="item.utility_value">
                                                        <SelectTrigger class="h-8">
                                                            <SelectValue placeholder="Select" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem v-for="option in utilityValueOptions" :key="`utility-card-${option.value}`" :value="option.value">{{ option.label }}</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div class="space-y-1 md:col-span-2">
                                                    <Label class="text-xs">19. Retention Period</Label>
                                                    <Input v-model="item.retention_total" class="h-8" placeholder="e.g., 5 years" />
                                                </div>
                                                <div class="space-y-1 md:col-span-2">
                                                    <Label class="text-xs">20. Disposition Provision</Label>
                                                    <Input v-model="item.provision" class="h-8" />
                                                </div>
                                            </div>
                                        </div>
                                    </details>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="border-2">
                        <CardContent class="pt-6">
                            <div class="flex flex-col gap-4">
                                <div v-if="!isFormValid" class="rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-900/10">
                                    <p class="text-sm font-medium text-amber-800 dark:text-amber-200">Required fields missing</p>
                                    <p class="text-xs text-amber-700 dark:text-amber-300 mt-1" v-if="missingFields.length > 0">
                                        Please complete: {{ missingFields.map((field) => field.label).join(', ') }}
                                    </p>
                                </div>

                                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-foreground">
                                            {{ isFormValid ? 'Ready to finalize NAP Form 1 PDF?' : 'Complete the form to continue' }}
                                        </p>
                                        <p class="text-xs text-muted-foreground mt-1">
                                            {{ isFormValid ? 'Use Sync PDF to update preview, then finalize and download the PDF' : 'Fill in all required fields above' }}
                                        </p>
                                    </div>
                                    <Button @click="openFinalizeDownloadDialog" :disabled="form.processing || isDownloading || !isFormValid" size="lg" class="sm:shrink-0">
                                        <Download class="mr-2 h-4 w-4" />
                                        {{ isDownloading ? 'Generating PDF...' : 'Finalize & Download PDF' }}
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Live Preview Pane: fixed, no scroll -->
            <div v-if="showPreview" class="hidden lg:block w-1/2 shrink-0 border-l border-border overflow-hidden">
                <div class="h-full">
                    <PdfPreviewPane
                        :pdf-url="inlinePdfUrl"
                        :has-generated-preview="hasPreviewDocument"
                        :is-syncing="isPreviewSyncing"
                        :error-message="previewErrorMessage"
                        iframe-title="NAP Form 1 PDF Preview"
                        @refresh="syncPdfPreview"
                    />
                </div>
            </div>
        </div>

        <Dialog v-model:open="finalizeDownloadDialogOpen">
            <DialogContent class="sm:max-w-115">
                <DialogHeader>
                    <DialogTitle>Finalize NAP Form 1 PDF</DialogTitle>
                    <DialogDescription>
                        This will regenerate NAP Form 1 using the current values and download the final PDF file.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter>
                    <Button variant="outline" @click="finalizeDownloadDialogOpen = false">Cancel</Button>
                    <Button @click="confirmFinalizeAndDownloadPdf" :disabled="form.processing || isDownloading">
                        <Download class="mr-2 h-4 w-4" />
                        {{ isDownloading ? 'Generating PDF...' : 'Confirm & Download' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
