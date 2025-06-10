<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';
import TextLink from '@/components/TextLink.vue';
</script>

<template>
    <Head title="Welcome" />
    <AuthLayout
        title="AI-Powered Payslip Processing"
        description="Automate loan eligibility checks by analyzing payslip data."
    >
        <div class="flex flex-col items-center justify-center space-y-4">
            <div class="flex w-full space-x-4">
                <TextLink :href="route('login')" class="w-full">
                    <Button variant="outline" class="w-full">
                        Login
                    </Button>
                </TextLink>
                <TextLink :href="route('register')" class="w-full">
                    <Button class="w-full">
                        Request Account
                    </Button>
                </TextLink>
                </div>
        </div>
    </AuthLayout>
</template>
