<script setup lang="ts">
import { Quote as QuoteIcon, RefreshCw } from 'lucide-vue-next';
import { useQuote } from '@/composables/useQuote';

const { quote, loading, refetch } = useQuote();
</script>

<template>
    <div
        class="overflow-hidden "
    >
        <!-- Loading State -->
        <div v-if="loading" class="animate-pulse space-y-3">
            <div class="h-4 w-3/4 rounded bg-white/10"></div>
            <div class="h-4 w-1/2 rounded bg-white/10"></div>
            <div class="h-3 w-1/4 rounded bg-white/10"></div>
        </div>

        <!-- Quote Content -->
        <div v-else-if="quote" class="space-y-3">
            <div class="flex items-start gap-2.5">
                <QuoteIcon class="mt-0.5 h-4 w-4 shrink-0 text-primary/60" />
                <p class="flex-1 text-xs leading-relaxed text-foreground/90">
                    {{ quote.content }}
                </p>
            </div>

            <div class="flex items-center justify-between">
                <p class="text-[10px] font-medium text-muted-foreground">
                    — {{ quote.author }}
                </p>

                <!-- <button
                    type="button"
                    @click="refetch"
                    class="rounded-lg p-1 text-muted-foreground transition-all duration-200 hover:bg-white/10 hover:text-primary focus:outline-none focus:ring-2 focus:ring-primary/50"
                    title="Get new quote"
                    aria-label="Get new quote"
                >
                    <RefreshCw class="h-3 w-3" />
                </button> -->
            </div>
        </div>

        <!-- Error State -->
        <div v-else class="space-y-2 text-center">
            <p class="text-xs text-muted-foreground">
                Unable to load quote
            </p>
            <button
                type="button"
                @click="refetch"
                class="text-[10px] font-medium text-primary transition-colors hover:text-primary/80"
            >
                Try again
            </button>
        </div>

        <!-- Decorative gradient overlay -->
        <div class="pointer-events-none absolute inset-0 bg-linear-to-br from-primary/5 to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100"></div>
    </div>
</template>

