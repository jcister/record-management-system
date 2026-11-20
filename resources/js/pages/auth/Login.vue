<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import FloatingLabelInput from '@/components/auth/FloatingLabelInput.vue';
import QuoteDisplay from '@/components/auth/QuoteDisplay.vue';
import TextLink from '@/components/common/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { google } from '@/routes/auth';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
const Art = '/Frame.png';

defineProps<{
    status?: string;
    error?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(store.url(), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>

    <Head title="Log in" />

    <!-- Split Container: left = art/design, right = login -->
    <div class="grid grid-cols-1 sm:grid-cols-2 min-h-screen place-items-center bg-background ">
        <!-- Left: Artwork / Design (placeholder for user to implement) -->
        <div class="hidden sm:flex relative w-full h-screen items-center justify-center overflow-hidden">
            <!-- Top-left favicon -->
            <img src="/favicon_1.png" alt="favicon" class="absolute top-4 left-4 w-6 h-6 z-20" />

            <!-- Full-size artwork on left -->
            <img :src="Art" alt="Artwork Placeholder" class="block w-full h-full object-cover"
                style="object-position: 0% 1%;" />
        </div>

        <!-- Right: Login -->
        <div class="flex flex-col w-full h-screen items-center justify-center p-8 md:p-10 lg:p-20">
            <div class="flex-1 flex items-center w-full ">
                <div class="w-full max-w-md space-y-6 md:justify-center">
                    <div class="space-y-2">
                        <p class="text-sm text-blue-600 font-bold">
                            CHED ONSE
                        </p>
                        <!-- <AppLogoIcon class-name="h-30 w-30 text-foreground" /> -->
                        <h1 class="text-3xl font-bold tracking-tight text-stone-800 dark:text-white sm:text-4xl">
                            Knowledge Management Information System
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            Sign in to CHED ONSE KMIS to continue to your account.
                        </p>
                        <p v-if="error" class="text-sm font-medium text-destructive">
                            {{ error }}
                        </p>
                    </div>

                    <!-- Google SSO Button -->
                    <Link :href="google().url"
                        class="group flex w-full items-center justify-center gap-3 rounded-xl border-2 border-border/50 bg-white/10 px-4 py-3.5 text-sm font-semibold backdrop-blur-sm transition-all duration-200 hover:border-border hover:bg-white/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 dark:border-border/30 dark:bg-white/5 dark:hover:bg-white/10">
                        <svg class="h-5 w-5 transition-transform duration-200 group-hover:scale-110" viewBox="0 0 24 24"
                            aria-hidden="true">
                            <path fill="#4285F4"
                                d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                            <path fill="#34A853"
                                d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                            <path fill="#FBBC05"
                                d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
                            <path fill="#EA4335"
                                d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
                        </svg>
                        <span>Continue with Google</span>
                    </Link>

                    <!-- Divider -->
                    <div class="relative">
                        <Separator class="bg-white/10" />
                        <div
                            class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 bg-background px-3 text-xs font-medium tracking-wide text-muted-foreground backdrop-blur-sm dark:bg-transparent">
                            OR
                        </div>
                    </div>

                    <!-- Login Form -->
                    <form @submit.prevent="submit" class="space-y-5">
                        <p v-if="form.errors.email || form.errors.password" class="text-sm font-medium text-destructive">
                            Invalid email or password.
                        </p>
                        <!-- Email Field -->
                        <FloatingLabelInput id="email" name="email" type="email" label="Email address" required
                            autofocus :tabindex="1" autocomplete="email" v-model="form.email" />

                        <!-- Password Field -->
                        <div class="space-y-2">
                            <FloatingLabelInput id="password" name="password" type="password" label="Password" required
                                :tabindex="2" autocomplete="current-password" show-password-toggle v-model="form.password" />
                            <div v-if="canResetPassword" class="text-right">
                                <TextLink :href="request()"
                                    class="text-xs font-medium transition-colors hover:text-primary" :tabindex="5">
                                    Forgot password?
                                </TextLink>
                            </div>
                        </div>

                        <!-- Remember Me -->
                        <div class="flex items-center gap-2">
                            <Checkbox id="remember" name="remember" :tabindex="3" v-model="form.remember" />
                            <Label for="remember" class="cursor-pointer text-sm font-medium">
                                Remember me
                            </Label>
                        </div>

                        <!-- Submit Button -->
                        <Button type="submit"
                            class="h-12 w-full border-2 border-primary/20 text-sm font-semibold cursor-pointer hover:border-primary/30 "
                            :tabindex="4" :disabled="form.processing" data-test="login-button">
                            <Spinner v-if="form.processing" class="mr-2" />
                            <span>{{ form.processing ? 'Signing in...' : 'Sign in' }}</span>
                        </Button>
                    </form>

                </div>
            </div>
            <div class="w-full flex mt-auto">
                <QuoteDisplay />
            </div>
        </div>

        <!-- Footer (spans both columns on small screens) -->
        <!-- <div class="md:col-span-2 text-center mt-4">
                <p class="text-sm text-muted-foreground">
                    CHED RO XI © 2026
                </p>
            </div> -->
    </div>
</template>
