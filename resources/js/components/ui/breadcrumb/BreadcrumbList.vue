<script lang="ts" setup>
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <ol
    data-slot="breadcrumb-list"
    :class="cn('text-muted-foreground flex flex-nowrap items-center gap-1.5 text-sm sm:gap-2.5 min-w-0 overflow-hidden whitespace-nowrap text-ellipsis', props.class)"
  >
    <slot />
  </ol>
</template>
