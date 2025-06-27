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
        <div class="w-full px-4 sm:px-6 lg:px-8 space-y-12">
            <!-- Hero Features Section -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12 max-w-5xl mx-auto">
                <div class="rounded-2xl bg-gradient-to-br from-blue-600/10 to-blue-800/20 border border-blue-500/20 p-6 transition-colors duration-300 hover:border-blue-400/40">
                    <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-blue-600/20 mb-4">
                        <FileText class="h-6 w-6 text-blue-400" />
                    </div>
                    <h3 class="font-bold text-lg text-blue-100 mb-2">Smart Analysis</h3>
                    <p class="text-blue-200/80 text-sm leading-relaxed">Advanced AI algorithms extract and analyze payslip data with precision</p>
                </div>
                
                <div class="rounded-2xl bg-gradient-to-br from-emerald-600/10 to-emerald-800/20 border border-emerald-500/20 p-6 transition-colors duration-300 hover:border-emerald-400/40">
                    <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-emerald-600/20 mb-4">
                        <Zap class="h-6 w-6 text-emerald-400" />
                    </div>
                    <h3 class="font-bold text-lg text-emerald-100 mb-2">Instant Results</h3>
                    <p class="text-emerald-200/80 text-sm leading-relaxed">Get loan eligibility results in seconds, not hours</p>
                </div>
                
                <div class="rounded-2xl bg-gradient-to-br from-violet-600/10 to-violet-800/20 border border-violet-500/20 p-6 transition-colors duration-300 hover:border-violet-400/40">
                    <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-violet-600/20 mb-4">
                        <Shield class="h-6 w-6 text-violet-400" />
                    </div>
                    <h3 class="font-bold text-lg text-violet-100 mb-2">Bank-Grade Security</h3>
                    <p class="text-violet-200/80 text-sm leading-relaxed">Enterprise-level encryption protects sensitive financial data</p>
                </div>
            </div>

            <!-- Main Access Portals -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12 max-w-6xl mx-auto">
                <!-- Staff Portal -->
                <Card class="bg-gradient-to-br from-slate-800/50 to-slate-900/50 border-slate-700/50 backdrop-blur-sm transition-colors duration-300 hover:border-blue-500/50">
                    <CardHeader class="text-center pb-6">
                        <div class="flex justify-center mb-6">
                            <div class="flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-blue-600/20 to-blue-800/30">
                                <UserCheck class="h-8 w-8 text-blue-400" />
                            </div>
                        </div>
                        <CardTitle class="text-2xl font-bold text-white mb-3">Staff Portal</CardTitle>
                        <CardDescription class="text-slate-300 text-base leading-relaxed">
                            Comprehensive dashboard for managing payslip processing workflows, analytics, and administrative operations.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="space-y-3">
                            <div class="flex items-center space-x-3 text-slate-200">
                                <Clock class="h-4 w-4 text-blue-400" />
                                <span>Real-time processing queue</span>
                            </div>
                            <div class="flex items-center space-x-3 text-slate-200">
                                <TrendingUp class="h-4 w-4 text-blue-400" />
                                <span>Advanced analytics dashboard</span>
                            </div>
                            <div class="flex items-center space-x-3 text-slate-200">
                                <Shield class="h-4 w-4 text-blue-400" />
                                <span>Secure user management</span>
                            </div>
                        </div>
                        
                        <div class="space-y-3 pt-4">
                            <TextLink :href="route('login')" class="w-full">
                                <Button class="w-full h-12 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-semibold rounded-xl">
                                    Staff Login
                                    <ChevronRight class="ml-2 h-4 w-4" />
                                </Button>
                            </TextLink>
                            <TextLink :href="route('register')" class="w-full">
                                <Button variant="outline" class="w-full h-12 border-slate-600 text-slate-300 hover:bg-slate-700 hover:text-white rounded-xl">
                                    Request Staff Account
                                </Button>
                            </TextLink>
                        </div>
                    </CardContent>
                </Card>

                <!-- Client Portal -->
                <Card class="bg-gradient-to-br from-slate-800/50 to-slate-900/50 border-slate-700/50 backdrop-blur-sm transition-colors duration-300 hover:border-emerald-500/50">
                    <CardHeader class="text-center pb-6">
                        <div class="flex justify-center mb-6">
                            <div class="flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-emerald-600/20 to-emerald-800/30">
                                <MessageCircle class="h-8 w-8 text-emerald-400" />
                            </div>
                        </div>
                        <CardTitle class="text-2xl font-bold text-white mb-3">Client Access</CardTitle>
                        <CardDescription class="text-slate-300 text-base leading-relaxed">
                            Upload payslips and receive instant loan eligibility assessments through our intelligent Telegram bot interface.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="space-y-3">
                            <div class="flex items-center space-x-3 text-slate-200">
                                <MessageCircle class="h-4 w-4 text-emerald-400" />
                                <span>Intuitive Telegram interface</span>
                            </div>
                            <div class="flex items-center space-x-3 text-slate-200">
                                <Zap class="h-4 w-4 text-emerald-400" />
                                <span>Instant eligibility reports</span>
                            </div>
                            <div class="flex items-center space-x-3 text-slate-200">
                                <Shield class="h-4 w-4 text-emerald-400" />
                                <span>Encrypted document processing</span>
                            </div>
                        </div>
                        
                        <div class="pt-4">
                            <a href="https://t.me/YourPayslipBot" target="_blank" rel="noopener noreferrer" class="block w-full">
                                <Button class="w-full h-12 bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-700 hover:to-emerald-800 text-white font-semibold rounded-xl">
                                    <MessageCircle class="mr-2 h-4 w-4" />
                                    Start on Telegram
                                    <ChevronRight class="ml-2 h-4 w-4" />
                                </Button>
                            </a>
                            <div class="flex items-center justify-center mt-3">
                                <span class="text-xs text-emerald-400/80 bg-emerald-600/10 px-3 py-1 rounded-full border border-emerald-600/20">
                                    Configure your bot in .env file
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>


        </div>
    </AuthLayout>
</template>
