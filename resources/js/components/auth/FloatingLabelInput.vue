<script setup lang="ts">
import { computed, ref } from 'vue';
import { cn } from '@/lib/utils';
import { Eye, EyeOff } from 'lucide-vue-next';

interface Props {
    id: string;
    name: string;
    label: string;
    type?: string;
    modelValue?: string;
    error?: string;
    placeholder?: string;
    required?: boolean;
    autofocus?: boolean;
    autocomplete?: string;
    tabindex?: number;
    showPasswordToggle?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    type: 'text',
    placeholder: '',
    required: false,
    autofocus: false,
    showPasswordToggle: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const isFocused = ref(false);
const internalValue = ref(props.modelValue || '');
const isPasswordVisible = ref(false);

const isFloating = computed(() => isFocused.value || internalValue.value.length > 0);

const inputType = computed(() => {
    if (props.showPasswordToggle && props.type === 'password') {
        return isPasswordVisible.value ? 'text' : 'password';
    }
    return props.type;
});

const togglePasswordVisibility = () => {
    isPasswordVisible.value = !isPasswordVisible.value;
};

const handleInput = (event: Event) => {
    const target = event.target as HTMLInputElement;
    internalValue.value = target.value;
    emit('update:modelValue', target.value);
};

const handleFocus = () => {
    isFocused.value = true;
};

const handleBlur = () => {
    isFocused.value = false;
};
</script>

<template>
    <div class="relative">
        <input
            :id="id"
            :name="name"
            :type="inputType"
            :value="internalValue"
            :required="required"
            :autofocus="autofocus"
            :autocomplete="autocomplete"
            :tabindex="tabindex"
            @input="handleInput"
            @focus="handleFocus"
            @blur="handleBlur"
            :class="cn(
                'peer h-14 w-full rounded-xl border-2 bg-white/5 px-4 pt-6 pb-2 text-base backdrop-blur-sm transition-all duration-200',
                'placeholder-transparent outline-none',
                'focus:border-primary focus:bg-white/10 focus:ring-2 focus:ring-primary/20',
                error
                    ? 'border-destructive focus:border-destructive focus:ring-destructive/20'
                    : 'border-border/50 dark:border-border/30',
                'dark:bg-white/5 dark:focus:bg-white/10',
                showPasswordToggle && 'pr-10'
            )"
            :placeholder="placeholder"
        />
        <label
            :for="id"
            :class="cn(
                'absolute left-4 top-1/2 -translate-y-1/2 cursor-text text-muted-foreground transition-all duration-200',
                'peer-focus:top-3 peer-focus:translate-y-0 peer-focus:text-xs peer-focus:font-medium peer-focus:text-primary',
                isFloating && 'top-3 translate-y-0 text-xs font-medium',
                error && 'text-destructive peer-focus:text-destructive'
            )"
        >
            {{ label }}{{ required ? ' *' : '' }}
        </label>
        <button
            v-if="showPasswordToggle"
            type="button"
            @click="togglePasswordVisibility"
            :aria-label="isPasswordVisible ? 'Hide password' : 'Show password'"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 rounded-sm p-1"
        >
            <Eye v-if="!isPasswordVisible" class="size-4" />
            <EyeOff v-else class="size-4" />
        </button>
        <div
            v-if="error"
            class="mt-1.5 text-xs font-medium text-destructive"
        >
            {{ error }}
        </div>
    </div>
</template>

