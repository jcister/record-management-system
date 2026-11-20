<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, nextTick } from 'vue';
import { AlertCircle, ArrowLeft, CheckCircle2, FileText, Image, X, Eye } from 'lucide-vue-next';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import ImagePreviewDialog from '@/components/common/ImagePreviewDialog.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useToast } from '@/composables/useToast';
import type { BreadcrumbItem } from '@/types';
import { approve as approveBatch, show as showBatch } from '@/actions/App/Http/Controllers/DisposalBatchController';

interface DocumentItem {
    name: string;
    path: string | null;
    uploaded_at: string | null;
    uploaded_by: string | null;
    mime_type: string | null;
    size: number | null;
}

interface Props {
    batch: {
        id: string;
        reference: string;
        status: string;
        file_count: number;
    };
    defaultData: {
        approval_notes: string;
        coa_name: string;
        coa_position: string;
        coa_designation: string;
    };
    approvalDocuments: DocumentItem[];
    maxPictures: number;
}

const props = defineProps<Props>();
const { success, error } = useToast();

const form = useForm({
    approval_notes: props.defaultData.approval_notes,
    coa_name: props.defaultData.coa_name,
    coa_position: props.defaultData.coa_position,
    coa_designation: props.defaultData.coa_designation,
    pictures: [] as File[],
});

interface PicturePreview {
    id: string;
    name: string;
    size: number;
    url: string;
    file: File;
}

const picturePreviews = ref<PicturePreview[]>([]);
const pictureInputRef = ref<HTMLInputElement | null>(null);
const previewDialog = ref(false);
const previewImageUrl = ref<string>('');
const previewImageName = ref<string>('');

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Disposal Management', href: '/disposal-management' },
    { title: 'Disposal Batches', href: '/disposal-batches' },
    { title: props.batch.reference, href: showBatch.url(props.batch.id) },
    { title: 'Approval Review', href: '' },
];

const remainingSlots = computed(() => Math.max(props.maxPictures - props.approvalDocuments.length - picturePreviews.value.length, 0));

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

const formatFileSize = (size: number | null) => {
    if (!size) return 'Unknown size';
    if (size < 1024) return `${size} B`;
    if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`;
    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
};

const handlePicturesChange = async (event: Event) => {
    const target = event.target as HTMLInputElement;
    const files = Array.from(target.files ?? []);

    if (files.length === 0) {
        return;
    }

    // Check for duplicate filenames
    const existingFileNames = new Set([
        ...picturePreviews.value.map((p) => p.name),
        ...props.approvalDocuments.map((d) => d.name),
    ]);

    const duplicateFiles = files.filter((file) => existingFileNames.has(file.name));
    const uniqueFiles = files.filter((file) => !existingFileNames.has(file.name));

    if (duplicateFiles.length > 0) {
        const duplicateNames = duplicateFiles.map((f) => f.name);
        const previewNames = duplicateNames.slice(0, 2).join(', ');
        const remainingCount = duplicateNames.length - 2;
        const suffix = remainingCount > 0 ? `, and ${remainingCount} more` : '';
        error(
            'Duplicate files detected',
            `Already attached: ${previewNames}${suffix}`
        );
    }

    if (uniqueFiles.length === 0) {
        if (target) {
            target.value = '';
        }
        return;
    }

    if (uniqueFiles.length > remainingSlots.value) {
        error(
            'Picture limit reached',
            `You can only add ${remainingSlots.value} more picture${remainingSlots.value === 1 ? '' : 's'}.`
        );
        if (target) {
            target.value = '';
        }
        return;
    }

    // Create preview URLs for the new files
    const newPreviews: PicturePreview[] = [];
    for (const file of uniqueFiles) {
        const url = URL.createObjectURL(file);
        newPreviews.push({
            id: `${Date.now()}-${Math.random()}-${file.name}`,
            name: file.name,
            size: file.size,
            url,
            file,
        });
    }

    // Add to existing previews and form pictures
    picturePreviews.value = [...picturePreviews.value, ...newPreviews];

    // Force reactivity update
    await nextTick();

    form.pictures = picturePreviews.value.map((p) => p.file);

    // Reset file input
    if (target) {
        target.value = '';
    }

    if (uniqueFiles.length > 0) {
        success(
            'Pictures added',
            `${uniqueFiles.length} picture${uniqueFiles.length === 1 ? '' : 's'} added successfully.`
        );
    }
};

const removePicture = (id: string) => {
    const preview = picturePreviews.value.find((p) => p.id === id);
    if (preview) {
        URL.revokeObjectURL(preview.url);
    }
    picturePreviews.value = picturePreviews.value.filter((p) => p.id !== id);
    form.pictures = picturePreviews.value.map((p) => p.file);
};

const clearSelectedPictures = () => {
    picturePreviews.value.forEach((preview) => {
        URL.revokeObjectURL(preview.url);
    });
    form.pictures = [];
    picturePreviews.value = [];
    if (pictureInputRef.value) {
        pictureInputRef.value.value = '';
    }
};

const openPreview = (preview: PicturePreview) => {
    previewImageUrl.value = preview.url;
    previewImageName.value = preview.name;
    previewDialog.value = true;
};

const closePreview = (value: boolean) => {
    previewDialog.value = value;
    if (!value) {
        previewImageUrl.value = '';
        previewImageName.value = '';
    }
};

const submitApproval = () => {
    form.post(approveBatch.url(props.batch.id), {
        preserveScroll: true,
        forceFormData: true,
        onError: (errors) => {
            error('Approval failed', Object.values(errors).join(' '));
        },
        onSuccess: () => {
            success('Batch approved', 'Approval details and documentation have been saved.');
            // Clean up object URLs
            picturePreviews.value.forEach((preview) => {
                URL.revokeObjectURL(preview.url);
            });
            form.reset('pictures');
            picturePreviews.value = [];
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Approval Review - ${batch.reference}`" />

        <div class="space-y-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Button variant="ghost" size="icon" @click="goBack">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                    <div>
                        <h1 class="text-2xl font-semibold text-foreground">
                            Approval Review
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            Provide documentation before final approval.
                        </p>
                    </div>
                </div>
                <Badge variant="outline" class="capitalize">
                    {{ batch.reference }}
                </Badge>
            </div>

            <!-- Guidance -->
            <Card class="border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-900/20">
                <CardContent class="pt-6">
                    <div class="flex items-start gap-3">
                        <FileText class="h-5 w-5 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" />
                        <div class="space-y-2 text-sm">
                            <p class="font-medium text-blue-900 dark:text-blue-100">
                                Approval Checklist
                            </p>
                            <ul class="list-disc list-inside text-blue-800 dark:text-blue-200 space-y-1">
                                <li>Confirm the COA contact who will receive the Endorsement Letter to COA.</li>
                                <li>Review the pre-filled position and designation (editable if needed).</li>
                                <li>Upload up to {{ maxPictures }} pictures documenting the disposal.</li>
                            </ul>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2 space-y-6">
                    <!-- COA Contact Details -->
                    <Card>
                        <CardHeader>
                            <CardTitle>COA Contact</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-2">
                                <Label for="coa-name">Name</Label>
                                <Input
                                    id="coa-name"
                                    v-model="form.coa_name"
                                    placeholder="Enter full name"
                                />
                            </div>
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="coa-position">Position</Label>
                                    <Input
                                        id="coa-position"
                                        v-model="form.coa_position"
                                        placeholder="State Auditor IV"
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        Prefilled per COA guidance.
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="coa-designation">Designation</Label>
                                    <Input
                                        id="coa-designation"
                                        v-model="form.coa_designation"
                                        placeholder="Team Leader"
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        Adjust if the COA contact serves a different role.
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Notes -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Approval Notes</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Textarea
                                v-model="form.approval_notes"
                                rows="5"
                                placeholder="Add any final remarks about the disposal..."
                            />
                        </CardContent>
                    </Card>

                    <!-- Picture Upload -->
                    <Card>
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <CardTitle>Pictures / Documentation</CardTitle>
                                <Button
                                    v-if="picturePreviews.length > 0"
                                    variant="ghost"
                                    size="sm"
                                    @click="clearSelectedPictures"
                                >
                                    Clear All
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <!-- Upload Button -->
                            <div v-if="remainingSlots > 0" class="rounded-lg border border-dashed p-4 text-center">
                                <label
                                    for="pictures"
                                    class="cursor-pointer inline-flex items-center justify-center rounded-md px-4 py-2 text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground"
                                >
                                    <Image class="mr-2 h-4 w-4" />
                                    Choose Files
                                </label>
                                <Input
                                    ref="pictureInputRef"
                                    id="pictures"
                                    type="file"
                                    accept="image/*"
                                    multiple
                                    class="hidden"
                                    @change="handlePicturesChange"
                                />
                                <p class="mt-2 text-xs text-muted-foreground">
                                    You can add up to {{ remainingSlots }} more picture{{ remainingSlots === 1 ? '' : 's' }} (maximum {{ maxPictures }} total).
                                </p>
                            </div>
                            <div v-else class="rounded-lg border border-dashed p-4 text-center">
                                <p class="text-sm text-muted-foreground">
                                    Maximum pictures reached ({{ maxPictures }}/{{ maxPictures }})
                                </p>
                            </div>

                            <!-- Picture Tiles -->
                            <div v-if="picturePreviews.length" class="space-y-3">
                                <p class="text-sm font-medium">
                                    Selected Pictures ({{ picturePreviews.length }})
                                </p>
                                <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                                    <div
                                        v-for="preview in picturePreviews"
                                        :key="preview.id"
                                        class="group relative aspect-square overflow-hidden rounded-lg border bg-muted"
                                    >
                                        <img
                                            :src="preview.url"
                                            :alt="preview.name"
                                            class="h-full w-full object-cover transition-transform group-hover:scale-105"
                                        />
                                        <div
                                            class="absolute inset-0 bg-black/60 opacity-0 transition-opacity group-hover:opacity-100 flex items-center justify-center gap-2"
                                        >
                                            <Button
                                                variant="secondary"
                                                size="icon"
                                                class="h-8 w-8"
                                                @click="openPreview(preview)"
                                            >
                                                <Eye class="h-4 w-4" />
                                            </Button>
                                            <Button
                                                variant="destructive"
                                                size="icon"
                                                class="h-8 w-8"
                                                @click="removePicture(preview.id)"
                                            >
                                                <X class="h-4 w-4" />
                                            </Button>
                                        </div>
                                        <div
                                            class="absolute bottom-0 left-0 right-0 bg-linear-to-t from-black/80 to-transparent p-2"
                                        >
                                            <p class="text-xs text-white truncate" :title="preview.name">
                                                {{ preview.name }}
                                            </p>
                                            <p class="text-xs text-white/70">
                                                {{ formatFileSize(preview.size) }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Existing Attachments & Summary -->
                <div class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Batch Summary</CardTitle>
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
                                <span class="text-muted-foreground">Status:</span>
                                <Badge variant="outline">{{ statusDisplayLabel(batch.status) }}</Badge>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Existing Documentation</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div v-if="approvalDocuments.length" class="space-y-3">
                                <div
                                    v-for="document in approvalDocuments"
                                    :key="document.path ?? document.name"
                                    class="rounded-md border p-3 text-sm"
                                >
                                    <p class="font-medium">{{ document.name }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ document.mime_type || 'Image' }}
                                        <span v-if="document.uploaded_at"> • Uploaded {{ document.uploaded_at }}</span>
                                    </p>
                                </div>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">
                                No documentation uploaded yet.
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent class="space-y-3">
                            <p class="text-sm text-muted-foreground">
                                Once you submit, the batch will be marked as approved and attachments will be stored for auditing.
                            </p>
                            <div class="flex flex-col gap-2">
                                <Button variant="outline" @click="goBack">
                                    Cancel
                                </Button>
                                <Button
                                    class="bg-emerald-600 hover:bg-emerald-700"
                                    :disabled="form.processing"
                                    @click="submitApproval"
                                >
                                    <CheckCircle2 class="mr-2 h-4 w-4" />
                                    Approve Batch
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>

        <!-- Image Preview Dialog -->
        <ImagePreviewDialog
            :open="previewDialog"
            :image-url="previewImageUrl"
            :image-name="previewImageName"
            @update:open="closePreview"
        />
    </AppLayout>
</template>
