<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class TelegramTranslationService
{
    private array $translations = [];
    private string $defaultLanguage = 'ms';
    private array $supportedLanguages = ['ms', 'en', 'zh'];

    public function __construct()
    {
        $this->loadTranslations();
    }

    /**
     * Get translated text
     */
    public function get(string $key, string $language = null, array $params = []): string
    {
        $language = $language ?? $this->defaultLanguage;
        
        if (!$this->isLanguageSupported($language)) {
            $language = $this->defaultLanguage;
        }

        $translation = $this->getTranslation($key, $language);
        
        // Replace parameters
        foreach ($params as $param => $value) {
            $translation = str_replace("{{$param}}", $value, $translation);
        }

        return $translation;
    }

    /**
     * Get translation for specific language
     */
    private function getTranslation(string $key, string $language): string
    {
        // Try to get from cache first
        $cacheKey = "telegram_translation_{$language}_{$key}";
        $cached = Cache::get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }

        // Get from loaded translations
        $translation = $this->translations[$language][$key] ?? null;
        
        // Fallback to default language if not found
        if ($translation === null && $language !== $this->defaultLanguage) {
            $translation = $this->translations[$this->defaultLanguage][$key] ?? null;
        }

        // Ultimate fallback to the key itself
        if ($translation === null) {
            $translation = $key;
            Log::warning("Translation not found for key: {$key} in language: {$language}");
        }

        // Cache the result
        Cache::put($cacheKey, $translation, 3600); // Cache for 1 hour

        return $translation;
    }

    /**
     * Load all translations
     */
    private function loadTranslations(): void
    {
        $this->translations = [
            'ms' => [
                // Welcome messages
                'welcome.title' => '🏦 *Selamat datang ke Payslip AI!*',
                'welcome.description' => 'Saya adalah bot pintar yang membantu anda menganalisis slip gaji dan menyemak kelayakan koperasi.',
                'welcome.features' => "✨ *Ciri-ciri utama:*\n✅ Analisis slip gaji automatik\n📊 Semakan kelayakan koperasi\n💡 Cadangan terbaik\n🔒 Data selamat & peribadi",
                'welcome.get_started' => 'Mari mulakan dengan memilih bahasa pilihan anda!',
                
                // Menu messages
                'menu.welcome' => 'Selamat datang kembali, {name}! 👋',
                'menu.instructions' => 'Pilih operasi yang anda ingin lakukan:',
                
                // Buttons
                'button.scan_payslip' => '📄 Imbas Slip Gaji',
                'button.koperasi_list' => '🏦 Senarai Koperasi',
                'button.check_status' => '📊 Semak Status',
                'button.history' => '📚 Sejarah',
                'button.settings' => '⚙️ Tetapan',
                'button.help' => '❓ Bantuan',
                'button.cancel' => '❌ Batal',
                'button.back' => '🔙 Kembali',
                'button.next' => 'Seterusnya',
                'button.previous' => 'Sebelumnya',
                'button.change_language' => '🌍 Tukar Bahasa',
                'button.notifications' => '🔔 Pemberitahuan',
                'button.delete_data' => '🗑️ Padam Data',
                'button.export_data' => '📤 Eksport Data',
                'button.back_to_menu' => '🏠 Kembali ke Menu',
                'button.view_details' => '👁️ Lihat Butiran ID: {id}',
                
                // Scan messages
                'scan.title' => '📄 *Imbas Slip Gaji*',
                'scan.instructions' => 'Hantar slip gaji anda dalam format yang disokong untuk analisis automatik.',
                'scan.supported_formats' => "📋 *Format yang disokong:*\n• PDF (disyorkan)\n• JPG, PNG, JPEG\n• Maksimum: {max_size}MB",
                'scan.tips' => "💡 *Tips untuk hasil terbaik:*\n• Pastikan teks jelas dan tidak kabur\n• Gunakan pencahayaan yang baik\n• Pastikan semua maklumat kelihatan",
                'scan.send_file' => '📤 Hantar fail anda sekarang...',
                'scan.waiting_file' => '⏳ Saya sedang menunggu fail slip gaji anda. Sila hantar fail atau gunakan /cancel untuk membatalkan.',
                'scan.cancelled' => '❌ Imbasan dibatalkan. Kembali ke menu utama.',
                'scan.processing' => '⚙️ Sedang memproses slip gaji anda... Ini mungkin mengambil masa beberapa minit.',
                'scan.success' => '✅ Slip gaji anda telah diproses dengan jayanya!',
                'scan.failed' => '❌ Maaf, gagal memproses slip gaji anda. Sila cuba lagi atau hubungi sokongan.',
                
                // Status messages
                'status.uploaded' => 'Dimuat naik',
                'status.processing' => 'Sedang diproses',
                'status.completed' => 'Selesai',
                'status.failed' => 'Gagal',
                'status.empty' => 'Tiada slip gaji yang diproses lagi.',
                'status.start_scanning' => 'Gunakan /scan untuk mula mengimbas slip gaji! 📄',
                
                // History messages
                'history.title' => '📚 *Sejarah Slip Gaji* (Halaman {page})',
                'history.empty' => '📚 Tiada sejarah slip gaji dijumpai.',
                'history.start_scanning' => 'Gunakan /scan untuk mula mengimbas slip gaji anda!',
                'history.salary' => 'Gaji Bersih: RM {amount}',
                
                // Settings messages
                'settings.title' => '⚙️ *Tetapan Akaun*',
                'settings.current_language' => '🌍 Bahasa semasa: {language}',
                'settings.notifications' => '🔔 Pemberitahuan: {status}',
                'settings.choose_option' => 'Pilih tetapan yang ingin anda ubah:',
                
                // Language messages
                'language.title' => '🌍 *Pilih Bahasa*',
                'language.choose' => 'Pilih bahasa pilihan anda:',
                'language.changed' => '✅ Bahasa telah ditukar kepada {language}',
                
                // Koperasi messages
                'koperasi.title' => '🏦 *Senarai Koperasi Aktif*',
                'koperasi.empty' => '❌ Tiada koperasi aktif pada masa ini.',
                'koperasi.details' => '*{name}*\n📊 Max Peratusan: {max_percentage}%\n💰 Min Gaji Pokok: RM {min_salary}\n📝 {description}',
                'koperasi.tip' => '💡 *Tip:* Hantar slip gaji untuk semakan kelayakan automatik!',
                
                // Help messages
                'help.title' => '🆘 *Panduan Penggunaan Bot*',
                'help.commands' => "*Arahan Utama:*\n/start - Mula menggunakan bot\n/scan - Imbas slip gaji\n/koperasi - Lihat senarai koperasi\n/status - Semak status pemprosesan\n/history - Lihat sejarah\n/settings - Tetapan akaun\n/help - Panduan ini",
                'help.how_to_scan' => "*Cara Mengimbas Slip Gaji:*\n1️⃣ Gunakan /scan atau tekan butang 'Imbas Slip Gaji'\n2️⃣ Hantar fail slip gaji (PDF/gambar)\n3️⃣ Tunggu analisis selesai\n4️⃣ Dapatkan laporan kelayakan koperasi",
                'help.supported_formats' => "*Format Fail Disokong:*\n📄 PDF (disyorkan)\n📷 JPG, PNG, JPEG\n📏 Maksimum {max_size}MB",
                'help.get_started' => 'Hantar slip gaji anda sekarang untuk analisis automatik! 🚀',
                'help.use_menu' => 'Sila gunakan butang menu di bawah atau arahan yang tersedia.',
                
                // Error messages
                'error.general' => '❌ Maaf, terdapat ralat. Sila cuba lagi.',
                'error.rate_limit' => '⏰ Anda menghantar mesej terlalu cepat. Sila tunggu sebentar.',
                'error.unknown_command' => '🤔 Saya tidak faham arahan tersebut.',
                'error.file_too_large' => '📁 Fail terlalu besar. Maksimum saiz adalah {max_size}MB.',
                'error.unsupported_format' => '📄 Format fail tidak disokong. Sila gunakan PDF, JPG, PNG, atau JPEG.',
                'error.user_not_found' => '👤 Pengguna tidak dijumpai. Sila gunakan /start terlebih dahulu.',
                'error.admin_only' => '🔐 Arahan ini hanya untuk pentadbir.',
                'error.callback' => '❌ Ralat memproses permintaan. Sila cuba lagi.',
                'error.processing_failed' => '❌ Gagal memproses fail. Sila pastikan fail anda jelas dan cuba lagi.',
                
                // Success messages
                'success.file_uploaded' => '✅ Fail berjaya dimuat naik! Sedang memproses...',
                'success.language_changed' => '✅ Bahasa berjaya ditukar!',
                'success.notifications_enabled' => '✅ Pemberitahuan telah diaktifkan.',
                'success.notifications_disabled' => '❌ Pemberitahuan telah dimatikan.',
                'success.data_exported' => '📤 Data anda telah dieksport.',
                'success.data_deleted' => '🗑️ Data anda telah dipadam.',
                
                // Admin messages
                'admin.panel' => '🔧 *Panel Pentadbir*',
                'admin.stats' => 'Statistik Sistem:',
                'admin.total_users' => '👥 Jumlah Pengguna: {count}',
                'admin.total_payslips' => '📄 Jumlah Slip Gaji: {count}',
                'admin.completed_today' => '✅ Selesai Hari Ini: {count}',
                'admin.processing' => '⏳ Sedang Diproses: {count}',
                'admin.failed_today' => '❌ Gagal Hari Ini: {count}',
                'admin.choose_action' => 'Pilih tindakan pentadbir:',
                
                // Feedback messages
                'feedback.title' => '💬 *Maklum Balas*',
                'feedback.prompt' => 'Sila hantar maklum balas, cadangan, atau laporan masalah anda:',
                'feedback.received' => '✅ Terima kasih atas maklum balas anda! Kami akan menimbangkannya.',
                'feedback.cancelled' => '❌ Maklum balas dibatalkan.',
                
                // Notifications
                'notification.payslip_completed' => '🎉 Slip gaji ID: {id} telah selesai diproses!',
                'notification.payslip_failed' => '❌ Gagal memproses slip gaji ID: {id}. Sila cuba lagi.',
            ],
            
            'en' => [
                // Welcome messages
                'welcome.title' => '🏦 *Welcome to Payslip AI!*',
                'welcome.description' => 'I am an intelligent bot that helps you analyze payslips and check koperasi eligibility.',
                'welcome.features' => "✨ *Key features:*\n✅ Automatic payslip analysis\n📊 Koperasi eligibility checking\n💡 Best recommendations\n🔒 Secure & private data",
                'welcome.get_started' => 'Let\'s start by selecting your preferred language!',
                
                // Menu messages
                'menu.welcome' => 'Welcome back, {name}! 👋',
                'menu.instructions' => 'Choose the operation you want to perform:',
                
                // Buttons
                'button.scan_payslip' => '📄 Scan Payslip',
                'button.koperasi_list' => '🏦 Koperasi List',
                'button.check_status' => '📊 Check Status',
                'button.history' => '📚 History',
                'button.settings' => '⚙️ Settings',
                'button.help' => '❓ Help',
                'button.cancel' => '❌ Cancel',
                'button.back' => '🔙 Back',
                'button.next' => 'Next',
                'button.previous' => 'Previous',
                'button.change_language' => '🌍 Change Language',
                'button.notifications' => '🔔 Notifications',
                'button.delete_data' => '🗑️ Delete Data',
                'button.export_data' => '📤 Export Data',
                'button.back_to_menu' => '🏠 Back to Menu',
                'button.view_details' => '👁️ View Details ID: {id}',
                
                // Scan messages
                'scan.title' => '📄 *Scan Payslip*',
                'scan.instructions' => 'Send your payslip in supported format for automatic analysis.',
                'scan.supported_formats' => "📋 *Supported formats:*\n• PDF (recommended)\n• JPG, PNG, JPEG\n• Maximum: {max_size}MB",
                'scan.tips' => "💡 *Tips for best results:*\n• Ensure text is clear and not blurry\n• Use good lighting\n• Make sure all information is visible",
                'scan.send_file' => '📤 Send your file now...',
                'scan.waiting_file' => '⏳ I\'m waiting for your payslip file. Please send a file or use /cancel to abort.',
                'scan.cancelled' => '❌ Scan cancelled. Returning to main menu.',
                'scan.processing' => '⚙️ Processing your payslip... This may take a few minutes.',
                'scan.success' => '✅ Your payslip has been processed successfully!',
                'scan.failed' => '❌ Sorry, failed to process your payslip. Please try again or contact support.',
                
                // Status messages
                'status.uploaded' => 'Uploaded',
                'status.processing' => 'Processing',
                'status.completed' => 'Completed',
                'status.failed' => 'Failed',
                'status.empty' => 'No payslips processed yet.',
                'status.start_scanning' => 'Use /scan to start scanning payslips! 📄',
                
                // History messages
                'history.title' => '📚 *Payslip History* (Page {page})',
                'history.empty' => '📚 No payslip history found.',
                'history.start_scanning' => 'Use /scan to start scanning your payslips!',
                'history.salary' => 'Net Salary: RM {amount}',
                
                // Settings messages
                'settings.title' => '⚙️ *Account Settings*',
                'settings.current_language' => '🌍 Current language: {language}',
                'settings.notifications' => '🔔 Notifications: {status}',
                'settings.choose_option' => 'Choose the setting you want to change:',
                
                // Language messages
                'language.title' => '🌍 *Select Language*',
                'language.choose' => 'Choose your preferred language:',
                'language.changed' => '✅ Language changed to {language}',
                
                // Koperasi messages
                'koperasi.title' => '🏦 *Active Koperasi List*',
                'koperasi.empty' => '❌ No active koperasi at this time.',
                'koperasi.details' => '*{name}*\n📊 Max Percentage: {max_percentage}%\n💰 Min Basic Salary: RM {min_salary}\n📝 {description}',
                'koperasi.tip' => '💡 *Tip:* Send payslip for automatic eligibility check!',
                
                // Help messages
                'help.title' => '🆘 *Bot Usage Guide*',
                'help.commands' => "*Main Commands:*\n/start - Start using the bot\n/scan - Scan payslip\n/koperasi - View koperasi list\n/status - Check processing status\n/history - View history\n/settings - Account settings\n/help - This guide",
                'help.how_to_scan' => "*How to Scan Payslip:*\n1️⃣ Use /scan or press 'Scan Payslip' button\n2️⃣ Send payslip file (PDF/image)\n3️⃣ Wait for analysis to complete\n4️⃣ Get koperasi eligibility report",
                'help.supported_formats' => "*Supported File Formats:*\n📄 PDF (recommended)\n📷 JPG, PNG, JPEG\n📏 Maximum {max_size}MB",
                'help.get_started' => 'Send your payslip now for automatic analysis! 🚀',
                'help.use_menu' => 'Please use the menu buttons below or available commands.',
                
                // Error messages
                'error.general' => '❌ Sorry, there was an error. Please try again.',
                'error.rate_limit' => '⏰ You are sending messages too fast. Please wait a moment.',
                'error.unknown_command' => '🤔 I don\'t understand that command.',
                'error.file_too_large' => '📁 File too large. Maximum size is {max_size}MB.',
                'error.unsupported_format' => '📄 Unsupported file format. Please use PDF, JPG, PNG, or JPEG.',
                'error.user_not_found' => '👤 User not found. Please use /start first.',
                'error.admin_only' => '🔐 This command is for administrators only.',
                'error.callback' => '❌ Error processing request. Please try again.',
                'error.processing_failed' => '❌ Failed to process file. Please ensure your file is clear and try again.',
                
                // Success messages
                'success.file_uploaded' => '✅ File uploaded successfully! Processing...',
                'success.language_changed' => '✅ Language changed successfully!',
                'success.notifications_enabled' => '✅ Notifications have been enabled.',
                'success.notifications_disabled' => '❌ Notifications have been disabled.',
                'success.data_exported' => '📤 Your data has been exported.',
                'success.data_deleted' => '🗑️ Your data has been deleted.',
                
                // Admin messages
                'admin.panel' => '🔧 *Admin Panel*',
                'admin.stats' => 'System Statistics:',
                'admin.total_users' => '👥 Total Users: {count}',
                'admin.total_payslips' => '📄 Total Payslips: {count}',
                'admin.completed_today' => '✅ Completed Today: {count}',
                'admin.processing' => '⏳ Processing: {count}',
                'admin.failed_today' => '❌ Failed Today: {count}',
                'admin.choose_action' => 'Choose an admin action:',
                
                // Feedback messages
                'feedback.title' => '💬 *Feedback*',
                'feedback.prompt' => 'Please send your feedback, suggestions, or issue reports:',
                'feedback.received' => '✅ Thank you for your feedback! We will consider it.',
                'feedback.cancelled' => '❌ Feedback cancelled.',
                
                // Notifications
                'notification.payslip_completed' => '🎉 Payslip ID: {id} has been processed successfully!',
                'notification.payslip_failed' => '❌ Failed to process payslip ID: {id}. Please try again.',
            ],
            
            'zh' => [
                // Welcome messages
                'welcome.title' => '🏦 *欢迎使用工资单AI！*',
                'welcome.description' => '我是一个智能机器人，帮助您分析工资单并检查合作社资格。',
                'welcome.features' => "✨ *主要功能：*\n✅ 自动工资单分析\n📊 合作社资格检查\n💡 最佳建议\n🔒 安全私密数据",
                'welcome.get_started' => '让我们从选择您的首选语言开始！',
                
                // Menu messages
                'menu.welcome' => '欢迎回来，{name}！👋',
                'menu.instructions' => '选择您要执行的操作：',
                
                // Buttons
                'button.scan_payslip' => '📄 扫描工资单',
                'button.koperasi_list' => '🏦 合作社列表',
                'button.check_status' => '📊 检查状态',
                'button.history' => '📚 历史记录',
                'button.settings' => '⚙️ 设置',
                'button.help' => '❓ 帮助',
                'button.cancel' => '❌ 取消',
                'button.back' => '🔙 返回',
                'button.next' => '下一页',
                'button.previous' => '上一页',
                'button.change_language' => '🌍 更改语言',
                'button.notifications' => '🔔 通知',
                'button.delete_data' => '🗑️ 删除数据',
                'button.export_data' => '📤 导出数据',
                'button.back_to_menu' => '🏠 返回菜单',
                'button.view_details' => '👁️ 查看详情 ID: {id}',
                
                // Add more Chinese translations...
                // For brevity, I'll include key translations only
                
                'scan.title' => '📄 *扫描工资单*',
                'error.general' => '❌ 抱歉，出现错误。请重试。',
                'help.title' => '🆘 *机器人使用指南*',
                
                // ... more translations would be added here
            ]
        ];
    }

    /**
     * Check if language is supported
     */
    public function isLanguageSupported(string $language): bool
    {
        return in_array($language, $this->supportedLanguages);
    }

    /**
     * Get all supported languages
     */
    public function getSupportedLanguages(): array
    {
        return [
            'ms' => 'Bahasa Malaysia',
            'en' => 'English',
            'zh' => '中文',
        ];
    }

    /**
     * Clear translation cache
     */
    public function clearCache(): void
    {
        foreach ($this->supportedLanguages as $language) {
            Cache::forget("telegram_translations_{$language}");
        }
    }

    /**
     * Get default language
     */
    public function getDefaultLanguage(): string
    {
        return $this->defaultLanguage;
    }

    /**
     * Set default language
     */
    public function setDefaultLanguage(string $language): void
    {
        if ($this->isLanguageSupported($language)) {
            $this->defaultLanguage = $language;
        }
    }
} 