<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
  <div class="flex flex-wrap sm:flex-nowrap sm:flex-row items-start sm:items-center gap-y-2 py-3 border-b last:border-b-0">
    <!-- Checkbox Skeleton -->
    <Skeleton class="shrink-0 h-4 w-4 rounded" />

    <!-- File Icon Skeleton -->
    <Skeleton class="h-5 w-5 rounded ml-2 flex-shrink-0" />

    <!-- Name Skeleton -->
    <Skeleton class="h-4 w-32 rounded ml-2 flex-1" />

    <!-- Type Skeleton -->
    <Skeleton class="h-4 w-16 rounded hidden md:block" />

    <!-- Size Skeleton -->
    <Skeleton class="h-4 w-20 rounded hidden lg:block" />

    <!-- Status Skeleton -->
    <Skeleton class="h-6 w-12 rounded" />

    <!-- Date Skeleton -->
    <Skeleton class="h-4 w-24 rounded hidden sm:block" />

    <!-- Actions Skeleton -->
    <Skeleton class="h-8 w-8 rounded ml-2 flex-shrink-0" />
  </div>
</template>
