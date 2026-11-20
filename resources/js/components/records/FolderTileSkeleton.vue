<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
  <Card class="h-full min-h-[10rem] md:min-h-[13rem]">
    <CardContent class="h-full p-2 md:p-4">
      <div class="flex h-full flex-col items-center text-center space-y-0.5 md:space-y-4">
        <!-- Folder Icon Skeleton -->
        <Skeleton class="h-14 w-14 md:h-20 md:w-20 rounded-lg" />

        <!-- Content Skeleton -->
        <div class="w-full flex-1 flex flex-col justify-center gap-3">
          <!-- Folder Name Skeleton -->
          <Skeleton class="h-4 w-3/4 mx-auto rounded" />

          <!-- Date Skeleton -->
          <Skeleton class="h-3 w-1/2 mx-auto rounded" />

          <!-- Count Info Skeleton -->
          <Skeleton class="h-3 w-2/3 mx-auto rounded" />
        </div>
      </div>
    </CardContent>
  </Card>
</template>
