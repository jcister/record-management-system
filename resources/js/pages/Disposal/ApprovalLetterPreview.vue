    <script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { ArrowLeft, FileText } from 'lucide-vue-next';

import { approvalLetterDownload, approvalLetterGenerate, show as showBatch } from '@/actions/App/Http/Controllers/DisposalBatchController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { useToast } from '@/composables/useToast';
import type { BreadcrumbItem } from '@/types';
import { formatDate } from '@/utils/format';

interface BatchSummary {
    id: string;
    reference: string;
    status: string;
    file_count: number;
    mode_of_disposal_label: string | null;
    approved_at: string | null;
}

interface DefaultData {
    letter_reference: string | null;
    letter_date: string;
    recipient_name: string;
    recipient_position: string | null;
    recipient_designation: string | null;
}

interface CurrentUser {
    id: string;
    name: string;
    email: string;
    position: string | null;
}

type ContactPerson = {
    id: string | null;
    name: string;
    email: string | null;
    position: string | null;
};

interface Props {
    batch: BatchSummary;
    defaultData: DefaultData;
    currentUser: CurrentUser;
    people: {
        approver: ContactPerson | null;
        inspector: ContactPerson | null;
        scheduler: ContactPerson | null;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Disposal Management', href: '/disposal-management' },
    { title: 'Disposal Batches', href: '/disposal-batches' },
    { title: props.batch.reference, href: showBatch.url(props.batch.id) },
    { title: 'Endorsement Letter to COA', href: '' },
];

const { success, error } = useToast();

const form = useForm({
    letter_reference: props.defaultData.letter_reference || '',
    letter_date: props.defaultData.letter_date,
    recipient_name: props.defaultData.recipient_name || '',
    recipient_position: props.defaultData.recipient_position || '',
    recipient_designation: props.defaultData.recipient_designation || '',
});

const isFormValid = computed(() => Boolean(form.recipient_name.trim() && form.letter_date.trim()));

const goBack = () => {
    router.visit(showBatch.url(props.batch.id));
};

const statusDisplayLabel = (status: string): string => {
    switch (status) {
        case 'scheduled':
            return 'Scheduled';
        case 'completed':
            return 'Verified';
        case 'inspected':
            return 'Inspected';
        case 'approved':
            return 'Approved';
        case 'rejected':
            return 'Rejected';
        default:
            return status.charAt(0).toUpperCase() + status.slice(1);
    }
};

const submit = () => {
    form.post(approvalLetterGenerate.url(props.batch.id), {
        preserveScroll: true,
        onSuccess: () => {
            allowNavigation.value = true;
            success('Endorsement Letter to COA generated', 'The PDF will download shortly.');
            setTimeout(() => {
                const url = approvalLetterDownload.url(props.batch.id, { query: { format: 'pdf' } });
                window.location.href = url;
            }, 700);
        },
        onError: (errors) => {
            error('Generation failed', Object.values(errors).join(' '));
        },
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
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Endorsement Letter to COA - ${batch.reference}`" />

        <div class="space-y-6 p-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Button variant="ghost" size="icon" @click="goBack">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                    <div>
                        <h1 class="text-2xl font-semibold text-foreground">Endorsement Letter to COA Preview</h1>
                        <p class="text-sm text-muted-foreground">Finalize the recipient details before generating the PDF.</p>
                    </div>
                </div>
                <Badge variant="outline">{{ statusDisplayLabel(batch.status) }}</Badge>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="flex items-center gap-2 text-base">
                            <FileText class="h-4 w-4 text-muted-foreground" />
                            Batch Summary
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Reference:</span>
                            <span class="font-medium">{{ batch.reference }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Files:</span>
                            <span class="font-medium">{{ batch.file_count }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Mode:</span>
                            <span class="font-medium">{{ batch.mode_of_disposal_label || 'Not set' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Approved:</span>
                            <span class="font-medium">{{ batch.approved_at ? formatDate(batch.approved_at) : 'Pending' }}</span>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base">Workflow Contacts</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Approver:</span>
                            <span class="font-medium">{{ people.approver?.name || 'Not assigned' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Inspector:</span>
                            <span class="font-medium">{{ people.inspector?.name || 'Not recorded' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Scheduler:</span>
                            <span class="font-medium">{{ people.scheduler?.name || 'Not recorded' }}</span>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Letter Details</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="letter_reference">Letter Reference</Label>
                            <Input id="letter_reference" v-model="form.letter_reference" placeholder="ENDORSEMENT-LETTER-COA-DB-20260306-0001" />
                        </div>
                        <div class="space-y-2">
                            <Label for="letter_date">Letter Date</Label>
                            <Input id="letter_date" v-model="form.letter_date" placeholder="March 4, 2026" />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="space-y-2 md:col-span-1">
                            <Label for="recipient_name">Recipient Name</Label>
                            <Input id="recipient_name" v-model="form.recipient_name" placeholder="COA Contact" />
                        </div>
                        <div class="space-y-2 md:col-span-1">
                            <Label for="recipient_position">Position</Label>
                            <Input id="recipient_position" v-model="form.recipient_position" placeholder="Supervising Auditor" />
                        </div>
                        <div class="space-y-2 md:col-span-1">
                            <Label for="recipient_designation">Designation</Label>
                            <Input id="recipient_designation" v-model="form.recipient_designation" placeholder="Commission on Audit Region XI" />
                        </div>
                    </div>

                    <div class="rounded-md border border-blue-200 bg-blue-50 p-4 text-sm text-blue-700 dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-100">
                        The Endorsement Letter to COA will summarize the disposal workflow and use these contact details for the salutation. You can update this information anytime.
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="text-sm text-muted-foreground">
                            Signed by {{ currentUser.name }} ({{ currentUser.position || 'Records staff' }})
                        </div>
                        <Button :disabled="form.processing || !isFormValid" @click="submit">
                            <FileText class="mr-2 h-4 w-4" />
                            Generate & Download
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
