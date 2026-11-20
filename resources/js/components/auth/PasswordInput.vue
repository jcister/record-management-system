<script setup lang="ts">
import { computed, ref } from 'vue';
import type { HTMLAttributes } from 'vue';
import { useVModel } from '@vueuse/core';
import { cn } from '@/lib/utils';
import { Eye, EyeOff } from 'lucide-vue-next';
import Input from '@/components/ui/input/Input.vue';

interface Props {
    defaultValue?: string | number;
    modelValue?: string | number;
    class?: HTMLAttributes['class'];
    id?: string;
    name?: string;
    placeholder?: string;
    required?: boolean;
    autofocus?: boolean;
    autocomplete?: string;
    tabindex?: number;
}

const props = defineProps<Props>();

const emits = defineEmits<{
    'update:modelValue': [payload: string | number];
}>();

const modelValue = useVModel(props, 'modelValue', emits, {
    passive: true,
    defaultValue: props.defaultValue,
});

const isPasswordVisible = ref(false);

const inputType = computed(() => (isPasswordVisible.value ? 'text' : 'password'));

const togglePasswordVisibility = () => {
    isPasswordVisible.value = !isPasswordVisible.value;
};
</script>

<template>
    <div class="relative">
        <Input
            v-model="modelValue"
            :type="inputType"
            :id="id"
            :name="name"
            :placeholder="placeholder"
            :required="required"
            :autofocus="autofocus"
            :autocomplete="autocomplete"
            :tabindex="tabindex"
            :class="cn('pr-10', props.class)"
        />
        <button
            type="button"
            @click="togglePasswordVisibility"
            :aria-label="isPasswordVisible ? 'Hide password' : 'Show password'"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 rounded-sm p-1"
        >
            <Eye v-if="!isPasswordVisible" class="size-4" />
            <EyeOff v-else class="size-4" />
        </button>
    </div>
</template>
