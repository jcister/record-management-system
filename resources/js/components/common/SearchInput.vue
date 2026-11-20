<script setup lang="ts">
import { computed } from 'vue';
import { Search, X } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';

const props = withDefaults(defineProps<{ modelValue?: string; placeholder?: string }>(), {
  modelValue: '',
  placeholder: 'Search...',
});

const emit = defineEmits<{ (e: 'update:modelValue', value: string): void }>();

const value = computed({
  get: () => props.modelValue ?? '',
  set: (v: string) => emit('update:modelValue', v),
});

const clear = () => emit('update:modelValue', '');
</script>

<template>
  <div class="relative w-full">
    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">
      <Search class="h-4 w-4" />
    </span>

    <Input v-model="value" :placeholder="props.placeholder" class="pl-9" />

    <button v-if="value" @click="clear" type="button" class="absolute inset-y-0 right-2 flex items-center text-sm text-muted-foreground">Clear</button>
  </div>
</template>
