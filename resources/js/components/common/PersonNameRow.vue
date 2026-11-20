<script setup lang="ts">
import { computed } from 'vue';
import InputError from '@/components/common/InputError.vue';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface Props {
    prefixLabel?: string;
    firstLabel?: string;
    middleLabel?: string;
    lastLabel?: string;
    extensionLabel?: string;
    prefixPlaceholder?: string;
    firstPlaceholder?: string;
    middlePlaceholder?: string;
    lastPlaceholder?: string;
    extensionPlaceholder?: string;
    options?: string[];
    required?: boolean;
    middleOptionalText?: string;
    extensionOptionalText?: string;
    disablePrefix?: boolean;
    disableFirst?: boolean;
    disableMiddle?: boolean;
    disableLast?: boolean;
    disableExtension?: boolean;
    layoutClass?: string;
    includeNoPrefixOption?: boolean;
    noPrefixLabel?: string;
    prefixFieldName?: string;
    firstFieldName?: string;
    middleFieldName?: string;
    lastFieldName?: string;
    extensionFieldName?: string;
    prefixError?: string;
    firstError?: string;
    middleError?: string;
    lastError?: string;
    extensionError?: string;
}

const DEFAULT_LAYOUT_CLASS = 'flex flex-wrap gap-3';

const DEFAULT_PREFIX_OPTIONS = Object.freeze([
    'Dr.',
    'Mr.',
    'Ms.',
    'Mrs.',
    'Engr.',
    'Atty.',
    'Prof.',
    'Hon.',
]);

const NO_PREFIX_VALUE = '__none__';

const props = withDefaults(defineProps<Props>(), {
    prefixLabel: 'Prefix',
    firstLabel: 'First Name',
    middleLabel: 'Middle Name',
    lastLabel: 'Last Name',
    extensionLabel: 'Extension',
    prefixPlaceholder: 'Select prefix',
    firstPlaceholder: 'e.g. Juan',
    middlePlaceholder: 'e.g. Cruz',
    lastPlaceholder: 'e.g. Dela Cruz',
    extensionPlaceholder: 'e.g. Jr.',
    options: undefined,
    required: true,
    middleOptionalText: 'Optional',
    extensionOptionalText: 'Optional',
    layoutClass: undefined,
    includeNoPrefixOption: true,
    noPrefixLabel: 'No Prefix',
    prefixFieldName: 'prefix',
    firstFieldName: 'first_name',
    middleFieldName: 'middle_name',
    lastFieldName: 'last_name',
    extensionFieldName: 'extension',
});

const containerClasses = computed(() => {
    return props.layoutClass && props.layoutClass.trim() !== ''
        ? props.layoutClass
        : DEFAULT_LAYOUT_CLASS;
});

const prefixOptions = computed(() => {
    if (props.options && props.options.length > 0) {
        return props.options;
    }

    return DEFAULT_PREFIX_OPTIONS;
});

const errorIdFor = (fieldName: string | undefined, fallback: string): string => {
    if (!fieldName || fieldName.trim() === '') {
        return `${fallback}-error`;
    }

    return `${fieldName}-error`;
};

const prefixErrorId = computed(() => errorIdFor(props.prefixFieldName, 'prefix'));
const firstErrorId = computed(() => errorIdFor(props.firstFieldName, 'first_name'));
const middleErrorId = computed(() => errorIdFor(props.middleFieldName, 'middle_name'));
const lastErrorId = computed(() => errorIdFor(props.lastFieldName, 'last_name'));
const extensionErrorId = computed(() => errorIdFor(props.extensionFieldName, 'extension'));

const emit = defineEmits<{
    (e: 'blur:first'): void;
    (e: 'blur:middle'): void;
    (e: 'blur:last'): void;
    (e: 'blur:extension'): void;
}>();

const prefixModel = defineModel<string>('prefix', { default: '' });
const prefixSelectValue = computed({
    get: () => {
        return prefixModel.value === '' ? NO_PREFIX_VALUE : prefixModel.value;
    },
    set: (value: string) => {
        prefixModel.value = value === NO_PREFIX_VALUE ? '' : value;
    },
});
const firstNameModel = defineModel<string>('first', { default: '' });
const middleNameModel = defineModel<string>('middle', { default: '' });
const lastNameModel = defineModel<string>('last', { default: '' });
const extensionModel = defineModel<string>('extension', { default: '' });
</script>

<template>
    <div :class="containerClasses">
        <div class="space-y-1 flex-none basis-[140px] sm:basis-[160px] md:basis-[180px] lg:basis-[200px]">
            <Label>
                {{ props.prefixLabel }}
            </Label>
            <Select v-model="prefixSelectValue" :disabled="props.disablePrefix">
                <SelectTrigger class="w-full">
                    <SelectValue :placeholder="props.prefixPlaceholder" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-if="props.includeNoPrefixOption" :value="NO_PREFIX_VALUE">
                        {{ props.noPrefixLabel }}
                    </SelectItem>
                    <SelectItem v-for="opt in prefixOptions" :key="opt" :value="opt">
                        {{ opt }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <input v-if="props.prefixFieldName" type="hidden" :name="props.prefixFieldName" :value="prefixModel" />
            <InputError :id="prefixErrorId" :message="props.prefixError" />
        </div>

        <div class="min-w-[200px] flex-1 space-y-1">
            <Label>
                {{ props.firstLabel }}
                <span v-if="props.required" class="text-destructive">*</span>
            </Label>
            <Input
                v-model="firstNameModel"
                :name="props.firstFieldName"
                :placeholder="props.firstPlaceholder"
                :disabled="props.disableFirst"
                :required="props.required"
                :aria-invalid="props.firstError ? 'true' : 'false'"
                :aria-describedby="props.firstError ? firstErrorId : undefined"
                @blur="emit('blur:first')"
                class="w-full"
            />
            <InputError :id="firstErrorId" :message="props.firstError" />
        </div>

        <div class="min-w-[200px] flex-1 space-y-1">
            <div class="flex items-center justify-between">
                <Label>{{ props.middleLabel }}</Label>
                <span class="text-xs text-muted-foreground">{{ props.middleOptionalText }}</span>
            </div>
            <Input
                v-model="middleNameModel"
                :name="props.middleFieldName"
                :placeholder="props.middlePlaceholder"
                :disabled="props.disableMiddle"
                :aria-invalid="props.middleError ? 'true' : 'false'"
                :aria-describedby="props.middleError ? middleErrorId : undefined"
                @blur="emit('blur:middle')"
                class="w-full"
            />
            <InputError :id="middleErrorId" :message="props.middleError" />
        </div>

        <div class="min-w-[200px] flex-1 space-y-1">
            <Label>
                {{ props.lastLabel }}
                <span v-if="props.required" class="text-destructive">*</span>
            </Label>
            <Input
                v-model="lastNameModel"
                :name="props.lastFieldName"
                :placeholder="props.lastPlaceholder"
                :disabled="props.disableLast"
                :required="props.required"
                :aria-invalid="props.lastError ? 'true' : 'false'"
                :aria-describedby="props.lastError ? lastErrorId : undefined"
                @blur="emit('blur:last')"
                class="w-full"
            />
            <InputError :id="lastErrorId" :message="props.lastError" />
        </div>

        <div class="min-w-[160px] flex-1 space-y-1">
            <div class="flex items-center justify-between">
                <Label>{{ props.extensionLabel }}</Label>
                <span class="text-xs text-muted-foreground">{{ props.extensionOptionalText }}</span>
            </div>
            <Input
                v-model="extensionModel"
                :name="props.extensionFieldName"
                :placeholder="props.extensionPlaceholder"
                :disabled="props.disableExtension"
                :aria-invalid="props.extensionError ? 'true' : 'false'"
                :aria-describedby="props.extensionError ? extensionErrorId : undefined"
                @blur="emit('blur:extension')"
                class="w-full"
            />
            <InputError :id="extensionErrorId" :message="props.extensionError" />
        </div>
    </div>
</template>
