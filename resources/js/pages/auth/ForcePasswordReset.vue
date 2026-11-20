<script setup lang="ts">
import { ref } from 'vue';
import { Form, Head } from '@inertiajs/vue3';

import FloatingLabelInput from '@/components/auth/FloatingLabelInput.vue';
import PasswordRequirements from '@/components/auth/PasswordRequirements.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import ForcePasswordResetController from '@/actions/App/Http/Controllers/Auth/ForcePasswordResetController';
import AppLogoIcon from '@/components/layout/AppLogoIcon.vue';

const password = ref('');
</script>

<template>
    <Head title="Set Your Password" />

    <!-- Centered Container -->
    <div class="flex min-h-screen items-center justify-center bg-background px-4 py-8">
        <div class="w-full max-w-md space-y-6">
            <!-- Glassmorphism Card -->
            <div
                class="space-y-6 rounded-2xl border border-white/10 bg-white/5 p-8 shadow-2xl backdrop-blur-xl dark:border-white/5 dark:bg-white/5">
                <div class="space-y-2 text-center">
                    <div class="flex items-center justify-center">
                        <AppLogoIcon class-name="h-30 w-30 text-foreground" />
                    </div>
                    <h1 class="text-3xl font-bold tracking-tight text-foreground">
                        Set Your Password
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Welcome! Please set a secure password for your account.
                    </p>
                </div>

                <!-- Password Reset Form -->
                <Form
                    v-bind="ForcePasswordResetController.update.form()"
                    v-slot="{ errors, processing }"
                    class="space-y-5"
                >
                    <!-- Password Requirements -->
                    <PasswordRequirements :model-value="password" />

                    <!-- New Password Field -->
                    <FloatingLabelInput
                        id="password"
                        v-model="password"
                        name="password"
                        type="password"
                        label="New Password"
                        :error="errors.password"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="new-password"
                        show-password-toggle
                    />



                    <!-- Confirm Password Field -->
                    <FloatingLabelInput
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        label="Confirm New Password"
                        :error="errors.password_confirmation"
                        required
                        :tabindex="2"
                        autocomplete="new-password"
                        show-password-toggle
                    />

                    <!-- Submit Button -->
                    <Button
                        type="submit"
                        :disabled="processing"
                        class="w-full rounded-xl py-3 text-base font-semibold shadow-lg transition-all duration-200 hover:scale-[1.02] hover:shadow-xl"
                        :tabindex="3"
                    >
                        <Spinner v-if="processing" class="mr-2 h-4 w-4" />
                        {{ processing ? 'Setting Password...' : 'Set Password' }}
                    </Button>
                </Form>
            </div>
        </div>
    </div>
</template>
