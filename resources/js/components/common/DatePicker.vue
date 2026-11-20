<script setup lang="ts">
import { getLocalTimeZone, parseDate, today } from '@internationalized/date';
import { Calendar as CalendarIcon, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import type { DateValue } from '@internationalized/date';

type DatePickerMode = 'date' | 'monthYear';

const props = withDefaults(defineProps<{
    placeholder?: string;
    disabled?: boolean;
    minValue?: DateValue;
    maxValue?: DateValue;
    allowManualEntry?: boolean;
    invalid?: boolean;
    mode?: DatePickerMode;
    monthYearOnly?: boolean;
}>(), {
    allowManualEntry: true,
    invalid: false,
    mode: 'date',
    monthYearOnly: false,
});

const emit = defineEmits<{
    (e: 'validation', payload: { isValid: boolean; message?: string }): void;
}>();

const modelValue = defineModel<string>({ default: '' });

const isMonthYearMode = computed(() => props.mode === 'monthYear');
const inputPlaceholder = computed(() => props.placeholder || (isMonthYearMode.value ? 'MM/YYYY' : 'MM/DD/YYYY'));
const inputMaxLength = computed(() => (isMonthYearMode.value ? 7 : 10));
const isMonthYearOnly = computed(() => isMonthYearMode.value && props.monthYearOnly);

const todayValue = today(getLocalTimeZone());
const visibleYear = ref(todayValue.year);

// Convert MM/DD/YY string to DateValue
const stringToDateValue = (dateStr: string) => {
    if (!dateStr) return undefined;

    if (isMonthYearMode.value) {
        if (dateStr.length !== 7) return undefined;

        const parts = dateStr.split('/');
        if (parts.length !== 2) return undefined;

        const month = parts[0].padStart(2, '0');
        const year = parts[1];

        if (month === '00' || parseInt(month, 10) > 12 || year.length !== 4 || Number.isNaN(Number(year))) {
            return undefined;
        }

        try {
            return parseDate(`${year}-${month}-01`);
        } catch {
            return undefined;
        }
    }

    // Accept MM/DD/YYYY (10) or MM/DD/YY (8)
    if (dateStr.length !== 10 && dateStr.length !== 8) return undefined;

    const parts = dateStr.split('/');
    if (parts.length !== 3) return undefined;

    const month = parts[0].padStart(2, '0');
    const day = parts[1].padStart(2, '0');
    let year = parseInt(parts[2], 10);

    if (parts[2].length === 2) {
        // Convert 2-digit year to 4-digit (00-49 = 2000-2049, 50-99 = 1950-1999)
        if (year < 50) {
            year += 2000;
        } else if (year < 100) {
            year += 1900;
        }
    }

    try {
        return parseDate(`${year}-${month}-${day}`);
    } catch {
        return undefined;
    }
};

// Convert DateValue to MM/DD/YY string
const dateValueToString = (date: any): string => {
    if (!date) return '';

    const month = String(date.month).padStart(2, '0');
    if (isMonthYearMode.value) {
        const year = String(date.year).padStart(4, '0');
        return `${month}/${year}`;
    }

    const day = String(date.day).padStart(2, '0');
    const year = String(date.year).padStart(4, '0');

    return `${month}/${day}/${year}`;
};

const emitValidation = (isValid: boolean, message?: string) => {
    emit('validation', { isValid, message });
};

const dateValue = computed({
    get: () => stringToDateValue(modelValue.value),
    set: (val) => {
        modelValue.value = val ? dateValueToString(val) : '';
        emitValidation(true);
    },
});

watch(
    () => [dateValue.value, isMonthYearOnly.value] as const,
    ([currentValue, monthYearOnly]) => {
        if (monthYearOnly && currentValue) {
            visibleYear.value = currentValue.year;
        }
    },
    { immediate: true }
);

const validateCurrentValue = (): boolean => {
    if (!modelValue.value) {
        emitValidation(true);
        return true;
    }


    if (isMonthYearMode.value) {
        if (!/^\d{2}\/\d{4}$/.test(modelValue.value)) {
            emitValidation(false, 'Enter the month in MM/YYYY format.');
            return false;
        }

        const [monthPart] = modelValue.value.split('/');
        const month = parseInt(monthPart, 10);
        if (month < 1 || month > 12) {
            emitValidation(false, 'Enter a valid month.');
            return false;
        }

        emitValidation(true);
        return true;
    }

    if (modelValue.value.length !== 10) {
        emitValidation(false, 'Enter the date in MM/DD/YYYY format.');
        return false;
    }

    const parsed = stringToDateValue(modelValue.value);
    if (!parsed) {
        emitValidation(false, 'Enter a valid calendar date.');
        return false;
    }

    emitValidation(true);
    return true;
};

const handleInput = (event: Event) => {
    if (!props.allowManualEntry) {
        return;
    }

    const target = event.target as HTMLInputElement;
    let value = target.value.replace(/[^\d/]/g, '');

    if (isMonthYearMode.value) {
        // Auto-format as MM/YYYY
        if (value.length > 2 && value[2] !== '/') {
            value = value.slice(0, 2) + '/' + value.slice(2);
        }

        if (value.length > 7) {
            value = value.slice(0, 7);
        }

        modelValue.value = value;

        if (!value) {
            emitValidation(true);
            return;
        }

        if (value.length < 7) {
            emitValidation(false, 'Enter the month in MM/YYYY format.');
            return;
        }

        validateCurrentValue();
        return;
    }

    // Auto-format as MM/DD/YYYY
    const parts = value.split('/');
    if (parts[0] && parts[0].length >= 2 && parts.length === 1) {
        value = parts[0].slice(0, 2) + '/' + parts[0].slice(2);
    }
    if (parts[1] && parts[1].length >= 2 && parts.length === 2) {
        value = parts[0] + '/' + parts[1].slice(0, 2) + '/' + parts[1].slice(2);
    }

    // Limit to MM/DD/YYYY format
    if (value.length > 10) {
        value = value.slice(0, 10);
    }

    modelValue.value = value;

    if (!value) {
        emitValidation(true);
        return;
    }

    if (value.length < 10) {
        emitValidation(false, 'Enter the date in MM/DD/YYYY format.');
        return;
    }

    validateCurrentValue();
};

const handleBlur = () => {
    if (!props.allowManualEntry) {
        emitValidation(true);
        return;
    }

    validateCurrentValue();
};

const navigationKeys = new Set([
    'Backspace',
    'Delete',
    'Tab',
    'ArrowLeft',
    'ArrowRight',
    'ArrowUp',
    'ArrowDown',
    'Home',
    'End',
]);

const handleKeyDown = (event: KeyboardEvent) => {
    // Prevent Enter from submitting form
    if (event.key === 'Enter') {
        event.preventDefault();
        return;
    }

    if (!props.allowManualEntry) {
        return;
    }

    if (event.ctrlKey || event.metaKey || event.altKey) {
        return;
    }

    if (navigationKeys.has(event.key)) {
        return;
    }

    if (/^\d$/.test(event.key)) {
        return;
    }

    if (event.key === '/' && !event.shiftKey) {
        return;
    }

    event.preventDefault();
};

const defaultPlaceholder = todayValue;

const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

const changeYear = (direction: -1 | 1) => {
    visibleYear.value += direction;
};

const isMonthDisabled = (monthIndex: number): boolean => {
    const month = monthIndex + 1;
    const candidate = parseDate(`${visibleYear.value}-${String(month).padStart(2, '0')}-01`);

    if (props.minValue && candidate.compare(props.minValue) < 0) {
        return true;
    }

    if (props.maxValue && candidate.compare(props.maxValue) > 0) {
        return true;
    }

    return false;
};

const isMonthSelected = (monthIndex: number): boolean => {
    const current = dateValue.value;
    if (!current) {
        return false;
    }

    return current.year === visibleYear.value && current.month === monthIndex + 1;
};

const selectMonth = (monthIndex: number) => {
    if (isMonthDisabled(monthIndex)) {
        return;
    }

    const month = monthIndex + 1;
    const selected = parseDate(`${visibleYear.value}-${String(month).padStart(2, '0')}-01`);
    dateValue.value = selected;
};
</script>

<template>
    <Popover>
        <div class="relative flex gap-2">
            <Input
                :model-value="modelValue"
                :placeholder="inputPlaceholder"
                :disabled="disabled"
                :readonly="!props.allowManualEntry"
                :maxlength="inputMaxLength"
                :class="cn('flex-1', props.invalid && 'border-destructive focus-visible:ring-destructive/50')"
                @input="handleInput"
                @blur="handleBlur"
                @keydown="handleKeyDown"
            />
            <PopoverTrigger as-child>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="disabled"
                        :class="cn(
                            'px-3 cursor-pointer hover:bg-secondary/80',
                            !dateValue && 'text-muted-foreground',
                        )"
                >
                    <CalendarIcon class="h-4 w-4" />
                </Button>
            </PopoverTrigger>
        </div>
        <PopoverContent class="w-auto p-0 !z-[70]">
            <div v-if="isMonthYearOnly" class="w-64 p-3">
                <div class="mb-3 flex items-center justify-between">
                    <Button type="button" variant="ghost" size="icon" class="h-8 w-8" @click="changeYear(-1)">
                        <ChevronLeft class="h-4 w-4" />
                    </Button>
                    <span class="text-sm font-medium">{{ visibleYear }}</span>
                    <Button type="button" variant="ghost" size="icon" class="h-8 w-8" @click="changeYear(1)">
                        <ChevronRight class="h-4 w-4" />
                    </Button>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <Button
                        v-for="(label, index) in monthNames"
                        :key="label"
                        type="button"
                        variant="outline"
                        class="h-9"
                        :disabled="isMonthDisabled(index)"
                        :class="[
                            isMonthSelected(index)
                                ? 'border-primary bg-primary text-primary-foreground hover:bg-primary'
                                : 'text-foreground'
                        ]"
                        @click="selectMonth(index)"
                    >
                        {{ label }}
                    </Button>
                </div>
            </div>
            <Calendar
                v-else
                v-model="dateValue"
                :initial-focus="true"
                :default-placeholder="defaultPlaceholder"
                :min-value="minValue"
                :max-value="maxValue"
                layout="month-and-year"
            />
        </PopoverContent>
    </Popover>
</template>
