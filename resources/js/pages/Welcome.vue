<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AuthLayout from '@/layouts/AuthLayout.vue';
import TextLink from '@/components/TextLink.vue';
import { UserCheck, MessageCircle, FileText, Zap, Shield, Clock, ChevronRight, Star, TrendingUp } from 'lucide-vue-next';
</script>

<template>
    <Head title="Welcome to Payslip AI" />
    <AuthLayout
        title="AI-Powered Payslip Processing"
        description="Streamline loan eligibility checks with intelligent payslip analysis. Fast, secure, and accurate processing for both staff and clients."
    >
        <div class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
            <!-- Hero Features Section -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                <div class="border rounded-lg p-4 bg-card hover:border-gray-500 transition-all duration-300">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-muted mb-3">
                        <FileText class="h-5 w-5 text-muted-foreground" />
                    </div>
                    <h3 class="font-semibold text-base mb-2">Smart Analysis</h3>
                    <p class="text-xs text-muted-foreground leading-relaxed">Advanced AI algorithms extract and analyze payslip data with precision</p>
                </div>
                
                <div class="border rounded-lg p-4 bg-card hover:border-gray-500 transition-all duration-300">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-muted mb-3">
                        <Zap class="h-5 w-5 text-muted-foreground" />
                    </div>
                    <h3 class="font-semibold text-base mb-2">Instant Results</h3>
                    <p class="text-xs text-muted-foreground leading-relaxed">Get loan eligibility results in seconds, not hours</p>
                </div>
                
                <div class="border rounded-lg p-4 bg-card hover:border-gray-500 transition-all duration-300">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-muted mb-3">
                        <Shield class="h-5 w-5 text-muted-foreground" />
                    </div>
                    <h3 class="font-semibold text-base mb-2">Bank-Grade Security</h3>
                    <p class="text-xs text-muted-foreground leading-relaxed">Enterprise-level encryption protects sensitive financial data</p>
                </div>
            </div>

            <!-- Main Access Portals -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Staff Portal -->
                <Card class="border-border/50 hover:border-border transition-colors">
                    <CardHeader class="pb-3">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="flex items-center justify-center w-8 h-8 rounded-md bg-primary/10">
                                <UserCheck class="h-4 w-4 text-primary" />
                            </div>
                            <div>
                                <CardTitle class="text-base font-medium">Staff Portal</CardTitle>
                                <CardDescription class="text-xs">
                                    Manage payslip processing and analytics
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="pt-0 space-y-3">
                        <div class="grid grid-cols-1 gap-1.5 text-xs text-muted-foreground">
                            <div class="flex items-center gap-2">
                                <Clock class="h-3 w-3" />
                                <span>Real-time processing</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <TrendingUp class="h-3 w-3" />
                                <span>Analytics dashboard</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <Shield class="h-3 w-3" />
                                <span>User management</span>
                            </div>
                        </div>
                        
                        <div class="flex gap-2">
                            <TextLink :href="route('login')" class="flex-1">
                                <Button size="sm" class="w-full h-7 text-xs">
                                    Login
                                    <ChevronRight class="ml-1 h-3 w-3" />
                                </Button>
                            </TextLink>
                            <TextLink :href="route('register')" class="flex-1">
                                <Button variant="outline" size="sm" class="w-full h-7 text-xs">
                                    Register
                                </Button>
                            </TextLink>
                        </div>
                    </CardContent>
                </Card>

                <!-- Client Portal -->
                <Card class="border-border/50 hover:border-border transition-colors">
                    <CardHeader class="pb-3">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="flex items-center justify-center w-8 h-8 rounded-md bg-primary/10">
                                <MessageCircle class="h-4 w-4 text-primary" />
                            </div>
                            <div>
                                <CardTitle class="text-base font-medium">Client Access</CardTitle>
                                <CardDescription class="text-xs">
                                    Upload payslips via Telegram bot
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="pt-0 space-y-3">
                        <div class="grid grid-cols-1 gap-1.5 text-xs text-muted-foreground">
                            <div class="flex items-center gap-2">
                                <MessageCircle class="h-3 w-3" />
                                <span>Telegram interface</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <Zap class="h-3 w-3" />
                                <span>Instant reports</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <Shield class="h-3 w-3" />
                                <span>Encrypted processing</span>
                            </div>
                        </div>
                        
                        <div class="space-y-2">
                            <a href="https://t.me/YourPayslipBot" target="_blank" rel="noopener noreferrer" class="block w-full">
                                <Button size="sm" class="w-full h-7 text-xs">
                                    <MessageCircle class="mr-1 h-3 w-3" />
                                    Start Bot
                                    <ChevronRight class="ml-1 h-3 w-3" />
                                </Button>
                            </a>
                            <div class="text-center">
                                <span class="text-xs text-muted-foreground bg-muted px-2 py-0.5 rounded text-nowrap">
                                    Configure in .env
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AuthLayout>
</template>
