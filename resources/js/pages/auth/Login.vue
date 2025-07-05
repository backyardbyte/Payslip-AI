<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, ArrowLeft, Info } from 'lucide-vue-next';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthBase class="w-md mx-auto" title="Log in to your account" description="Enter your email and password below to log in">
        <Head title="Log in" />

        <div class="mb-4">
            <TextLink :href="route('home')" class="inline-flex items-center text-sm text-muted-foreground hover:text-foreground transition-colors">
                <ArrowLeft class="h-4 w-4 mr-2" />
                Back to Home
            </TextLink>
        </div>

        <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <!-- Test Credentials Infobox -->
        <Card class="mb-6 bg-blue-50 border-blue-200 dark:bg-blue-950/50 dark:border-blue-800">
            <CardHeader class="pb-3">
                <CardTitle class="text-sm font-medium text-blue-700 dark:text-blue-300 flex items-center gap-2">
                    <Info class="h-4 w-4" />
                    Test Credentials
                </CardTitle>
                <CardDescription class="text-xs text-blue-600 dark:text-blue-400">
                    Use these accounts for testing purposes
                </CardDescription>
            </CardHeader>
            <CardContent class="pt-0">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div class="space-y-1">
                        <div class="font-medium text-blue-700 dark:text-blue-300">Super Admin</div>
                        <div class="text-blue-600 dark:text-blue-400">admin@test.com</div>
                        <div class="text-blue-600 dark:text-blue-400">Password123</div>
                    </div>
                    <div class="space-y-1">
                        <div class="font-medium text-blue-700 dark:text-blue-300">Manager</div>
                        <div class="text-blue-600 dark:text-blue-400">operations@test.com</div>
                        <div class="text-blue-600 dark:text-blue-400">Password123</div>
                    </div>
                    <div class="space-y-1">
                        <div class="font-medium text-blue-700 dark:text-blue-300">Admin</div>
                        <div class="text-blue-600 dark:text-blue-400">manager@test.com</div>
                        <div class="text-blue-600 dark:text-blue-400">Password123</div>
                    </div>
                    <div class="space-y-1">
                        <div class="font-medium text-blue-700 dark:text-blue-300">Operator</div>
                        <div class="text-blue-600 dark:text-blue-400">operator@test.com</div>
                        <div class="text-blue-600 dark:text-blue-400">Password123</div>
                    </div>
                    <div class="space-y-1">
                        <div class="font-medium text-blue-700 dark:text-blue-300">Demo User</div>
                        <div class="text-blue-600 dark:text-blue-400">demo@test.com</div>
                        <div class="text-blue-600 dark:text-blue-400">Password123</div>
                    </div>
                    <div class="space-y-1">
                        <div class="font-medium text-blue-700 dark:text-blue-300">Test User</div>
                        <div class="text-blue-600 dark:text-blue-400">test@example.com</div>
                        <div class="text-blue-600 dark:text-blue-400">password</div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="email"
                        v-model="form.email"
                        placeholder="email@example.com"
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="password">Password</Label>
                        <TextLink v-if="canResetPassword" :href="route('password.request')" class="text-sm" :tabindex="5">
                            Forgot password?
                        </TextLink>
                    </div>
                    <Input
                        id="password"
                        type="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        v-model="form.password"
                        placeholder="Password"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="flex items-center justify-between">
                    <Label for="remember" class="flex items-center space-x-3">
                        <Checkbox id="remember" v-model="form.remember" :tabindex="3" />
                        <span>Remember me</span>
                    </Label>
                </div>

                <Button type="submit" class="mt-4 w-full" :tabindex="4" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Log in
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Don't have an account?
                <TextLink :href="route('register')" :tabindex="5">Sign up</TextLink>
            </div>
        </form>
    </AuthBase>
</template>
