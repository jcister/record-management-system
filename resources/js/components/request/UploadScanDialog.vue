<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { apiClient } from '@/lib/axios';
import requestsApi from '@/routes/api/requests';
import { useToast } from '@/composables/useToast';
import { Upload } from 'lucide-vue-next';
import type { File } from '@/types/schema';
import {
    buildUppercaseNamedFile,
    doesSelectedFileMatchRecordName,
    normalizeFileNameInput,
    resolveRecordAutoSelection,
} from '@/lib/upload-file-dialog/fileHelpers';

const props = defineProps<{
    requestId: number;
    availableRecords: File[];
    blockedReason?: string;
}>();

const emit = defineEmits<{
    uploaded: [];
}>();

const open = defineModel<boolean>('open', { default: false });

const { success, error } = useToast();

const uploading = ref(false);
const notes = ref('');
const selectedFile = ref<globalThis.File | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const selectedRecordId = ref('');
const hasRecords = computed(() => (props.availableRecords?.length ?? 0) > 0);
const hasSingleRecord = computed(() => (props.availableRecords?.length ?? 0) === 1);
const selectedRecord = computed(() => props.availableRecords?.find(record => record.id === selectedRecordId.value) ?? null);
const matchStatus = ref<'idle' | 'matched' | 'ambiguous' | 'none'>('idle');
const targetRecordName = computed(() => {
    if (!selectedRecord.value) {
        return '';
    }
    return (selectedRecord.value.file_name || '').trim();
});
const fileNameMatches = computed(() => {
    if (!selectedFile.value || !selectedRecord.value) {
        return true;
    }

    return doesSelectedFileMatchRecordName(selectedFile.value, targetRecordName.value);
});
const fileNameMismatchMessage = computed(() => {
    if (fileNameMatches.value || !selectedRecord.value || !selectedFile.value) {
        return null;
    }

    const label = normalizeFileNameInput(selectedRecord.value.file_name || '') || 'linked record';
    return `Uploaded file name must match "${label}" exactly before the extension.`;
});

const onFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.files?.length) {
        selectedFile.value = target.files[0];
    }
};

const autoSelectRecord = () => {
    if (!hasRecords.value) {
        selectedRecordId.value = '';
        matchStatus.value = 'idle';
        return;
    }
    if (!selectedFile.value) {
        selectedRecordId.value = hasSingleRecord.value ? props.availableRecords[0].id : '';
        matchStatus.value = 'idle';
        return;
    }

    const { selectedRecordId: resolvedRecordId, matchStatus: resolvedMatchStatus } = resolveRecordAutoSelection(
        props.availableRecords,
        selectedFile.value,
    );
    selectedRecordId.value = resolvedRecordId;
    matchStatus.value = resolvedMatchStatus;
};

const submit = async () => {
    if (!selectedFile.value) {
        error('Please select a PDF or image file');
        return;
    }

    if (!hasRecords.value) {
        error('Link at least one record before uploading a scan');
        return;
    }

    if (!selectedRecordId.value) {
        error('Select the record this scan should update');
        return;
    }

    if (!fileNameMatches.value) {
        error(fileNameMismatchMessage.value || 'Uploaded file name must match the linked record.');
        return;
    }

    uploading.value = true;

    try {
        const fileToUpload = buildUppercaseNamedFile(selectedFile.value as globalThis.File);

        const formData = new FormData();
        formData.append('file', fileToUpload);
        formData.append('target_file_id', selectedRecordId.value);
        if (notes.value) {
            formData.append('notes', notes.value);
        }

        await apiClient.post(requestsApi.uploadScan.url(props.requestId), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        success('Draft uploaded successfully', 'Click Submit for Review when ready.');
        emit('uploaded');
        open.value = false;
    } catch (e: unknown) {
        const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
        const firstError = err.response?.data?.errors
            ? Object.values(err.response.data.errors)[0]?.[0]
            : undefined;
        error(firstError || err.response?.data?.message || 'Failed to upload scan');
    } finally {
        uploading.value = false;
    }
};

watch(open, (val) => {
    if (!val) {
        selectedFile.value = null;
        notes.value = '';
        if (!hasSingleRecord.value) {
            selectedRecordId.value = '';
        }
        if (fileInput.value) {
            fileInput.value.value = '';
        }
        autoSelectRecord();
        return;
    }

    autoSelectRecord();
});

watch([selectedFile, () => props.availableRecords], () => {
    autoSelectRecord();
}, { immediate: true });
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md w-[calc(100vw-2rem)] max-w-lg max-h-[90vh] overflow-y-auto">
            <DialogHeader class="gap-1 sticky top-0 bg-background pb-2">
                <DialogTitle>Upload Scanned File</DialogTitle>
            </DialogHeader>

            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <div class="flex flex-col gap-1.5">
                    <Label>Target Record</Label>
                    <template v-if="hasRecords">
                        <div v-if="selectedRecord" class="rounded-md border bg-muted/30 px-3 py-2">
                            <div class="text-sm font-medium">{{ selectedRecord.file_name }}</div>
                            <div class="text-xs text-muted-foreground">{{ selectedRecord.record_id }}</div>
                            <p class="text-xs text-emerald-600 mt-1" v-if="selectedFile && matchStatus === 'matched'">
                                Matched automatically because the file name matches this record.
                            </p>
                            <p class="text-xs text-muted-foreground mt-1" v-else>
                                Upload a file whose name matches this record (before its extension).
                            </p>
                        </div>
                        <div v-else-if="matchStatus === 'none'" class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                            No linked record matches the uploaded name. Rename the file to match a linked record exactly (before the extension).
                        </div>
                        <div v-else-if="matchStatus === 'ambiguous'" class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                            More than one linked record shares this name. Please resolve duplicates or adjust the file name.
                        </div>
                        <div v-else class="rounded-md border bg-muted/20 px-3 py-2 text-xs text-muted-foreground">
                            Select a file to automatically match it to a linked record.
                        </div>
                    </template>
                    <p v-if="selectedRecord" class="text-xs text-muted-foreground">
                        This scan will become the next version of <strong>{{ selectedRecord.file_name }}</strong>
                        once approved.
                    </p>
                    <p v-else-if="!hasRecords" class="text-xs text-destructive">
                        {{ props.blockedReason || 'Link a file from Records before uploading scans.' }}
                    </p>
                </div>

                <div class="flex flex-col gap-1.5">
                    <Label for="scan-file">Scanned File</Label>
                    <div class="flex items-center justify-center rounded-md border-2 border-dashed border-border p-6 cursor-pointer hover:border-primary/50 transition-colors"
                        @click="fileInput?.click()">
                        <div class="flex flex-col items-center gap-2 text-center">
                            <Upload class="h-8 w-8 text-muted-foreground" />
                            <p v-if="selectedFile" class="text-sm font-medium">{{ selectedFile.name }}</p>
                            <p v-else class="text-sm text-muted-foreground">Click to select a PDF or image file</p>
                        </div>
                    </div>
                    <input ref="fileInput" type="file" accept="application/pdf,image/*" class="hidden" @change="onFileChange" />
                    <p class="text-xs text-muted-foreground">
                        The file name (before its extension) must match the linked record name exactly.
                    </p>
                    <p v-if="fileNameMismatchMessage" class="text-xs text-destructive">
                        {{ fileNameMismatchMessage }}
                    </p>
                </div>

                <div class="flex flex-col gap-1.5">
                    <Label for="scan-notes">Notes (optional)</Label>
                    <Textarea id="scan-notes" v-model="notes" placeholder="Add any notes about this scan" rows="2" />
                </div>

                <DialogFooter class="sticky bottom-0 bg-background pt-2">
                    <Button type="button" variant="outline" @click="open = false">Cancel</Button>
                    <Button type="submit"
                        :disabled="uploading || !selectedFile || !selectedRecordId || !hasRecords || !fileNameMatches">
                        {{ uploading ? 'Uploading...' : 'Upload' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
