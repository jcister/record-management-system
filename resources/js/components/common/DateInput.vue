<script setup lang="ts">
import { computed } from 'vue';
import { Input } from '@/components/ui/input';

const props = defineProps<{
    placeholder?: string;
    disabled?: boolean;
}>();

const modelValue = defineModel<string>({ default: '' });

const handleInput = (event: Event) => {
    const target = event.target as HTMLInputElement;
    let value = target.value.replace(/[^\d/]/g, '');

    // Auto-format as MM/DD/YY
    const parts = value.split('/');
    if (parts[0] && parts[0].length >= 2 && parts.length === 1) {
        value = parts[0].slice(0, 2) + '/' + parts[0].slice(2);
    }
    if (parts[1] && parts[1].length >= 2 && parts.length === 2) {
        value = parts[0] + '/' + parts[1].slice(0, 2) + '/' + parts[1].slice(2);
    }

    // Limit to MM/DD/YY format
    if (value.length > 8) {
        value = value.slice(0, 8);
    }

    modelValue.value = value;
    target.value = value;
};

const handleBlur = () => {
    // Validate format on blur
    const parts = modelValue.value.split('/');
    if (parts.length === 3) {
        const month = parseInt(parts[0]);
        const day = parseInt(parts[1]);
        if (month < 1 || month > 12 || day < 1 || day > 31) {
            modelValue.value = '';
        }
    }
};
</script>

<template>
    <Input
        :value="modelValue"
        :placeholder="placeholder || 'MM/DD/YY'"
        :disabled="disabled"
        maxlength="8"
        @input="handleInput"
        @blur="handleBlur"
    />
</template>
