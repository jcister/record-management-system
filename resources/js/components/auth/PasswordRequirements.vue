<script setup lang="ts">
import { computed } from 'vue';
import { Check, X } from 'lucide-vue-next';

interface Props {
    modelValue?: string;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: '',
});

// Password requirement checks
const hasMinLength = computed(() => (props.modelValue?.length ?? 0) >= 8);
const hasUppercase = computed(() => /[A-Z]/.test(props.modelValue ?? ''));
const hasNumber = computed(() => /[0-9]/.test(props.modelValue ?? ''));
const hasSpecialChar = computed(() => /[\p{P}\p{S}]/u.test(props.modelValue ?? ''));

interface Requirement {
    label: string;
    met: boolean;
}

const requirements = computed((): Requirement[] => [
    { label: 'At least 8 characters', met: hasMinLength.value },
    { label: 'One uppercase letter', met: hasUppercase.value },
    { label: 'One number', met: hasNumber.value },
    { label: 'One special character (!@#$%^&*...)', met: hasSpecialChar.value },
]);
</script>

<template>
    <div class="space-y-2.5 rounded-xl border border-border/30 bg-muted/20 p-4 backdrop-blur-sm dark:border-border/20 dark:bg-muted/10">
        <p class="text-sm font-medium text-foreground/90">Password requirements:</p>
        <ul class="space-y-2">
            <li
                v-for="(requirement, index) in requirements"
                :key="index"
                class="flex items-center gap-2 text-sm transition-colors duration-200"
            >
                <Check
                    v-if="requirement.met"
                    :size="16"
                    class="shrink-0 text-green-600 dark:text-green-500"
                />
                <X
                    v-else
                    :size="16"
                    class="shrink-0 text-muted-foreground/50"
                />
                <span
                    :class="[
                        requirement.met
                            ? 'text-foreground/90 font-medium'
                            : 'text-muted-foreground',
                    ]"
                >
                    {{ requirement.label }}
                </span>
            </li>
        </ul>
    </div>
</template>
