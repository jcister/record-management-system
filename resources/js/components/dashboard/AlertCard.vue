<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { AlertCircle } from 'lucide-vue-next';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

interface Props {
    title: string;
    count: number;
    description?: string;
    route: string;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    description: undefined,
    loading: false,
});

const handleCardClick = () => {
    router.visit(props.route);
};
</script>

<template>
    <Card
        class="cursor-pointer transition-all hover:shadow-lg"
        :class="{
            'border-amber-500 bg-amber-50 dark:bg-amber-950/20': count > 0,
            'border-green-500 bg-green-50 dark:bg-green-950/20': count === 0,
        }"
        @click="handleCardClick"
    >
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">
                {{ title }}
            </CardTitle>
            <AlertCircle
                class="h-4 w-4"
                :class="{
                    'text-amber-500': count > 0,
                    'text-green-500': count === 0,
                }"
            />
        </CardHeader>
        <CardContent>
            <Skeleton v-if="props.loading" class="h-8 w-10" />
            <div v-else class="text-2xl font-bold" :class="{ 'text-amber-600 dark:text-amber-400': count > 0 }">
                {{ count }}
            </div>
            <Skeleton v-if="props.loading" class="mt-1 h-4 w-44" />
            <CardDescription v-else class="mt-1">
                {{ description ?? (count === 0 ? 'All caught up' : 'Attention needed') }}
            </CardDescription>
        </CardContent>
    </Card>
</template>
