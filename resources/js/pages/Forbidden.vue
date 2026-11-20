<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();

const returnHref = computed(() => {
    const isAuthenticated = Boolean((page.props as Record<string, unknown>)?.isAuthenticated);

    return isAuthenticated ? '/requests/myrequests' : '/login';
});
</script>

<template>
    <Head title="Access Denied" />

    <div class="min-h-screen bg-slate-100 text-slate-900">
        <div class="mx-auto flex min-h-screen max-w-3xl items-center px-6 py-12">
            <div class="w-full rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
                <p class="text-sm font-semibold tracking-[0.2em] text-slate-500">403</p>
                <h1 class="mt-3 text-3xl font-bold">Access denied</h1>
                <p class="mt-4 text-slate-600">
                    You do not have permission to view this page.
                </p>

                <div class="mt-8 flex gap-3">
                    <Link
                        :href="returnHref"
                        class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700"
                    >
                        Return
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
