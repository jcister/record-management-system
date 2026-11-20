<script setup lang="ts">
import { ref, watch, onMounted, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Tags, X, Info } from 'lucide-vue-next';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
    DialogDescription,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tooltip, TooltipProvider, TooltipTrigger, TooltipContent } from '@/components/ui/tooltip';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Badge } from '@/components/ui/badge';
import DatePicker from '@/components/common/DatePicker.vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
    SelectLabel,
} from '@/components/ui/select';
import TagManagementModal from '@/components/common/TagManagementModal.vue';
import { apiClient } from '@/lib/axios';
import requestsApi from '@/routes/api/requests';
import { useToast } from '@/composables/useToast';
import type { Department } from '@/types/schema';

interface Folder {
    id: string;
    name: string;
}

const props = defineProps<{
    departments: Department[];
}>();

const open = defineModel<boolean>('open', { default: false });

const { success, error } = useToast();

const submitting = ref(false);
const documentTypes = ref<Folder[]>([]);
const loadingDocumentTypes = ref(false);
const showMetadataTagsModal = ref(false);

interface Tag {
    id: number;
    name: string;
}

const allTags = ref<Tag[]>([]);

const loadAllTags = async () => {
    try {
        const response = await apiClient.get<Tag[]>('/api/tags');
        allTags.value = Array.isArray(response.data) ? response.data : [];
    } catch (err) {
        console.error('Failed to load tags:', err);
    }
};

const metadataTags = computed(() => {
    return form.value.metadata_tag_ids
        .map(id => allTags.value.find(tag => tag.id === id))
        .filter((tag): tag is Tag => tag !== undefined);
});

const isFormValid = computed(() => {
    return !!form.value.document_type &&
        !!form.value.request_title &&
    !!form.value.purpose &&
        !!form.value.required_format &&
    !!form.value.department_id && !isTitleTooLong.value;
});

const removeMetadataTag = (tagId: number) => {
    form.value.metadata_tag_ids = form.value.metadata_tag_ids.filter(id => id !== tagId);
};

const handleTagCreated = (tag: Tag) => {
    // Add the newly created tag to our local allTags list
    allTags.value.unshift(tag);
};

const generateUUID = (): string => {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
    });
};

const form = ref({
    request_id: generateUUID(),
    document_type: '',
    file_number: '',
    request_title: '',
    start_date: '',
    end_date: '',
    purpose: '',
    priority: 'standard',
    required_format: '',
    department_id: '',
    metadata_tag_ids: [] as number[],
    requires_scan: false,
});

const SUBJECT_TITLE_WORD_LIMIT = 6;

const countWords = (value: string): number => {
    if (!value) return 0;
    return value.trim().split(/\s+/).filter(Boolean).length;
};

const titleWordCount = computed(() => countWords(form.value.request_title || ''));

const isTitleTooLong = computed(() => titleWordCount.value > SUBJECT_TITLE_WORD_LIMIT);

const trimTitleToLimit = (): void => {
    const words = (form.value.request_title || '').trim().split(/\s+/).filter(Boolean);
    if (words.length > SUBJECT_TITLE_WORD_LIMIT) {
        form.value.request_title = words.slice(0, SUBJECT_TITLE_WORD_LIMIT).join(' ');
    }
};

const romanNumeralRegex = /^(?=[ivxlcdm]+$)m{0,4}(cm|cd|d?c{0,3})(xc|xl|l?x{0,3})(ix|iv|v?i{0,3})$/i;

const toSmartTitleCase = (value: string): string => {
    return value
        .trim()
        .replace(/\s+/g, ' ')
        .split(/(\s+|-|')/)
        .map((segment) => {
            if (segment.trim() === '' || segment === '-' || segment === "'") {
                return segment;
            }

            if (/^[a-z]\.?$/i.test(segment)) {
                return segment.charAt(0).toUpperCase() + (segment.endsWith('.') ? '.' : '');
            }

            if (romanNumeralRegex.test(segment)) {
                return segment.toUpperCase();
            }

            // Preserve manual capitalization for acronyms and mixed-case tokens.
            if (/[A-Z]/.test(segment) && segment !== segment.toLowerCase()) {
                return segment;
            }

            return segment.charAt(0).toUpperCase() + segment.slice(1).toLowerCase();
        })
        .join('');
};

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

const applyRequestTitleFormatting = (): void => {
    form.value.request_title = toSmartTitleCase(form.value.request_title || '');
    trimTitleToLimit();
};

const applyPurposeSentenceCase = (): void => {
    form.value.purpose = toSentenceCase(form.value.purpose || '');
};

const isUrgent = computed({
    get: () => form.value.priority === 'urgent',
    set: (val: boolean) => { form.value.priority = val ? 'urgent' : 'standard'; },
});

const errors = ref<Record<string, string[]>>({});
const dateRangeError = ref('');

const parseMonthYear = (value: string): Date | null => {
    if (!value || value.length !== 7) {
        return null;
    }

    const [monthPart, yearPart] = value.split('/');
    const month = Number(monthPart);
    const year = Number(yearPart);

    if (Number.isNaN(month) || Number.isNaN(year) || month < 1 || month > 12 || yearPart.length !== 4) {
        return null;
    }

    return new Date(year, month - 1, 1);
};

const validateDateRange = (): boolean => {
    const start = parseMonthYear(form.value.start_date);
    const end = parseMonthYear(form.value.end_date);

    if (!start || !end) {
        dateRangeError.value = '';
        return true;
    }

    if (end >= start) {
        dateRangeError.value = '';
        return true;
    }

    dateRangeError.value = 'End date must be after the start date.';
    return false;
};

watch(() => [form.value.start_date, form.value.end_date], () => {
    validateDateRange();
});

const loadDocumentTypes = async () => {
    loadingDocumentTypes.value = true;
    try {
        const response = await apiClient.get<{ data: Folder[] }>('/api/requests/document-types');
        documentTypes.value = response.data.data ?? [];
    } catch (e) {
        console.error('Failed to load document types:', e);
        documentTypes.value = [];
    } finally {
        loadingDocumentTypes.value = false;
    }
};

const resetForm = () => {
    form.value = {
        request_id: generateUUID(),
        document_type: '',
        file_number: '',
        request_title: '',
        start_date: '',
        end_date: '',
        purpose: '',
        priority: 'standard',
        required_format: '',
        department_id: '',
        metadata_tag_ids: [],
        requires_scan: false,
    };
    errors.value = {};
    dateRangeError.value = '';
};

onMounted(() => {
    loadDocumentTypes();
});

watch(open, (val) => {
    if (!val) {
        resetForm();
    } else {
        loadAllTags();
    }
});

const submit = async () => {
    submitting.value = true;
    errors.value = {};

    try {
        applyRequestTitleFormatting();
        applyPurposeSentenceCase();

        if (!validateDateRange()) {
            submitting.value = false;
            return;
        }

        const payload = {
            ...form.value,
            file_number: form.value.file_number || null,
            start_date: form.value.start_date || null,
            end_date: form.value.end_date || null,
            department_id: String(form.value.department_id),
            priority: form.value.priority,
            requires_scan: Boolean(form.value.requires_scan),
        };

        console.log('Submitting request with payload:', payload);

        await apiClient.post(requestsApi.store.url(), payload);

        success('Request submitted successfully');
        open.value = false;
        router.reload();
    } catch (e: unknown) {
        const err = e as { response?: { status?: number; data?: { errors?: Record<string, string[]>; message?: string } } };
        if (err.response?.status === 422 && err.response?.data?.errors) {
            errors.value = err.response.data.errors;
        } else {
            error('Failed to submit request');
        }
        console.error('Request submission error:', e);
    } finally {
        submitting.value = false;
    }
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="w-[calc(100vw-2rem)] max-w-lg p-0">
            <DialogHeader class="p-4 sm:p-6 pb-0">
                <DialogTitle>Request Record</DialogTitle>
                <DialogDescription class="text-sm">
                    Provide the subject details and desired formats so the archives team can locate the correct record.
                </DialogDescription>
            </DialogHeader>

            <form class="flex flex-col gap-4 max-h-[calc(100vh-180px)] sm:max-h-[calc(90vh-200px)] overflow-y-auto px-2 sm:px-4 mx-2"
                @submit.prevent="submit">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1.5">
                        <Label for="document_type">Document Type <span class="text-destructive">*</span></Label>
                        <Select v-model="form.document_type">
                            <SelectTrigger id="document_type" class="w-full cursor-pointer hover:bg-accent/90" :disabled="loadingDocumentTypes">
                                <SelectValue placeholder="Select Document Type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectLabel v-if="loadingDocumentTypes">Loading document types...</SelectLabel>
                                <SelectLabel v-else-if="documentTypes.length === 0">No document types found
                                </SelectLabel>
                                <template v-else>
                                    <SelectItem v-for="folder in documentTypes" :key="folder.id" :value="folder.name">
                                        {{ folder.name }}
                                    </SelectItem>
                                </template>
                            </SelectContent>
                        </Select>
                        <p v-if="errors.document_type" class="text-xs text-destructive">{{ errors.document_type[0] }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <Label for="file_number">Record ID <span class="text-xs text-muted-foreground font-normal">(optional)</span></Label>
                        <Input id="file_number" class="w-full" v-model="form.file_number"
                            placeholder="Enter Record ID" />
                        <p v-if="errors.file_number" class="text-xs text-destructive">{{ errors.file_number[0] }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1.5">
                        <Label>File Format <span class="text-destructive">*</span></Label>
                        <Select v-model="form.required_format">
                            <SelectTrigger class="w-full w-full cursor-pointer hover:bg-accent/90">
                                <SelectValue placeholder="Select Format " cla />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="Retrieve Original Copy">Retrieve Original Copy</SelectItem>
                                <SelectItem value="Reproduce Hardcopy">Reproduce Hardcopy</SelectItem>
                                <SelectItem value="Electronic Copy Only">Electronic Copy Only</SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="errors.required_format" class="text-xs text-destructive">{{ errors.required_format[0]
                        }}</p>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label>Division <span class="text-destructive">*</span></Label>
                        <Select v-model="form.department_id">
                            <SelectTrigger class="w-full w-full cursor-pointer hover:bg-accent/90">
                                <SelectValue placeholder="Select Division" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="dept in departments" :key="dept.id" :value="dept.id" class="w-full cursor-pointer hover:bg-accent/90">
                                    {{ dept.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="errors.department_id" class="text-xs text-destructive">{{ errors.department_id[0] }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <div class="flex items-center gap-2">
                        <Label>Date Range <span class="text-xs text-muted-foreground font-normal">(optional)</span></Label>
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <button type="button" class="inline-flex items-center justify-center p-0.5">
                                        <Info class="h-4 w-4 text-muted-foreground" />
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p class="text-xs">date range of the document when it was created/released</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                    <div class="flex flex-col sm:grid sm:grid-cols-[1fr_auto_1fr] gap-2 sm:gap-4 items-start">
                        <div class="flex flex-col gap-1.5 w-full">
                            <DatePicker v-model="form.start_date" mode="monthYear" :allow-manual-entry="true"
                                :month-year-only="true" />
                            <p v-if="errors.start_date" class="text-xs text-destructive">{{ errors.start_date[0] }}</p>
                        </div>
                        <div class="text-muted-foreground hidden sm:flex h-10 items-center">→</div>
                        <div class="flex flex-col gap-1.5 w-full">
                            <DatePicker v-model="form.end_date" mode="monthYear" :allow-manual-entry="true"
                                :month-year-only="true" :disabled="!form.start_date" />
                            <p v-if="errors.end_date" class="text-xs text-destructive">{{ errors.end_date[0] }}</p>
                        </div>
                    </div>
                    <p v-if="dateRangeError" class="text-xs text-destructive">{{ dateRangeError }}</p>
                </div>



                <div class="flex flex-col gap-1.5">
                    <Label for="request_title">Subject Title <span class="text-destructive">*</span></Label>
                    <Input id="request_title" class="w-full" v-model="form.request_title"
                        placeholder="Enter Subject Title" @blur="applyRequestTitleFormatting"
                        @keydown.enter.prevent="applyRequestTitleFormatting" />
                    <div class="flex items-center justify-between">
                        <p v-if="errors.request_title" class="text-xs text-destructive">{{ errors.request_title[0] }}</p>
                        <p :class="['text-xs', isTitleTooLong ? 'text-destructive' : 'text-muted-foreground']">
                            {{ titleWordCount }} / {{ SUBJECT_TITLE_WORD_LIMIT }} words
                        </p>
                    </div>
                    <p v-if="isTitleTooLong" class="text-xs text-destructive">Subject title is too long — keep it brief (max {{ SUBJECT_TITLE_WORD_LIMIT }} words).</p>
                </div>



                <div class="flex flex-col gap-1.5">
                    <Label for="purpose">Purpose <span class="text-destructive">*</span></Label>
                    <Textarea id="purpose" class="w-full" v-model="form.purpose"
                        placeholder="State the Purpose of the Request" rows="2"
                        @blur="applyPurposeSentenceCase" @keyup.enter="applyPurposeSentenceCase" />
                    <p v-if="errors.purpose" class="text-xs text-destructive">{{ errors.purpose[0] }}</p>
                </div>



                <div class="flex flex-col gap-1.5">
                    <Label for="metadata_tags">Categories & Keywords</Label>
                    <Button type="button" variant="outline" class="w-full justify-start cursor-pointer"
                        @click="showMetadataTagsModal = true">
                        <Tags class="h-4 w-4 mr-2" />
                        {{ metadataTags.length > 0 ? `${metadataTags.length} selected` : 'Select Categories & Keywords'
                        }}
                    </Button>
                    <div v-if="metadataTags.length > 0" class="flex flex-wrap gap-2">
                        <Badge v-for="tag in metadataTags" :key="tag.id" variant="secondary"
                            class="flex items-center gap-1 px-2 py-1">
                            <span>{{ tag.name }}</span>
                            <Button type="button" variant="ghost" size="icon" class="h-4 w-4 p-0 hover:bg-transparent"
                                @click="removeMetadataTag(tag.id)">
                                <X class="h-3 w-3" />
                            </Button>
                        </Badge>
                    </div>
                    <p class="text-xs text-muted-foreground">Add categories, topics, or keywords to help organize and
                        find this request
                        (optional).</p>
                    <p v-if="errors.metadata_tag_ids" class="text-xs text-destructive">{{ errors.metadata_tag_ids[0] }}
                    </p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div
                        class="flex items-center gap-2 rounded border border-dashed border-muted-foreground/30 bg-muted/20 p-2 cursor-pointer"
                        @click="isUrgent = !isUrgent">
                        <Checkbox id="is_urgent" v-model="isUrgent" @click.stop  class="cursor-pointer"/>
                        <div class="flex-1">
                            <Label for="is_urgent" class="text-xs font-medium cursor-pointer">Mark as Urgent</Label>
                            <p v-if="errors.priority" class="text-xs text-destructive">{{ errors.priority[0] }}</p>
                        </div>
                    </div>
                    <div
                        class="flex items-center gap-2 rounded border border-dashed border-muted-foreground/30 bg-muted/20 p-2 cursor-pointer"
                        @click="() => {
                            form.requires_scan = !form.requires_scan;
                            // console.log('Requires scan toggled to:', form.requires_scan);
                        }">
                        <Checkbox id="requires_scan" v-model="form.requires_scan" @click.stop class="cursor-pointer"/>
                        <div class="flex-1">
                            <Label for="requires_scan" class="text-xs font-medium cursor-pointer">Will Return Updated Copy</Label>
                            <p v-if="errors.requires_scan" class="text-xs text-destructive">{{ errors.requires_scan[0]
                            }}
                            </p>
                        </div>
                    </div>
                </div>
            </form>

            <DialogFooter class="flex-col sm:flex-row gap-2 sm:gap-0 pt-0 px-4 sm:px-6 pb-4 sm:pb-6">
                <Button type="button" variant="outline" class="w-full sm:w-auto" @click="open = false">Cancel</Button>
                <Button type="button" class="w-full sm:w-auto" :disabled="submitting || !isFormValid" @click="submit">
                    {{ submitting ? 'Submitting...' : 'Submit Request' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Tag Management Modal -->
    <TagManagementModal v-model:open="showMetadataTagsModal" v-model:selected-tag-ids="form.metadata_tag_ids"
        type="metadata" title="Categories & Keywords"
        description="Select existing tags or create new categories and keywords to help organize this request."
        :allow-create="false" @tag-created="handleTagCreated" />
</template>
