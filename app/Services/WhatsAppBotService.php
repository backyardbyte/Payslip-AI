<?php

namespace App\Services;

use App\Models\Koperasi;
use App\Models\User;
use App\Models\Payslip;
use App\Jobs\ProcessPayslip;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

class WhatsAppBotService
{
    private string $baseApiUrl;
    private string $accessToken;
    private string $phoneNumberId;
    private string $webhookVerifyToken;
    private array $messageHandlers = [];
    private array $interactiveHandlers = [];
    private array $mediaHandlers = [];

    public function __construct()
    {
        $this->accessToken = config('services.whatsapp.access_token');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id');
        $this->webhookVerifyToken = config('services.whatsapp.webhook_verify_token');
        
        // Only throw exception if actively trying to use the service
        if ($this->accessToken && $this->phoneNumberId) {
            $this->baseApiUrl = "https://graph.facebook.com/v18.0/{$this->phoneNumberId}";
        }
    }

    /**
     * Validate that the service is properly configured
     */
    private function validateConfiguration(): void
    {
        if (!$this->accessToken || !$this->phoneNumberId) {
            throw new \Exception('WhatsApp Bot credentials not configured');
        }
    }

    /**
     * Set up bot message handlers
     */
    public function setupBot(): void
    {
        // Register message handlers
        $this->messageHandlers['text'] = function ($message, $from) {
            Log::info('Received WhatsApp text message', ['from' => $from, 'text' => $message['text']['body']]);
            $this->handleTextMessage($message, $from);
        };

        $this->messageHandlers['document'] = function ($message, $from) {
            Log::info('Received WhatsApp document', ['from' => $from]);
            $this->handleDocumentMessage($message, $from);
        };

        $this->messageHandlers['image'] = function ($message, $from) {
            Log::info('Received WhatsApp image', ['from' => $from]);
            $this->handleImageMessage($message, $from);
        };

        $this->messageHandlers['interactive'] = function ($message, $from) {
            Log::info('Received WhatsApp interactive message', ['from' => $from]);
            $this->handleInteractiveMessage($message, $from);
        };
    }

    /**
     * Process webhook update from WhatsApp
     */
    public function processWebhookUpdate(array $data): void
    {
        $this->validateConfiguration();
        
        if (!isset($data['entry'])) {
            return;
        }

        foreach ($data['entry'] as $entry) {
            if (!isset($entry['changes'])) {
                continue;
            }

            foreach ($entry['changes'] as $change) {
                if ($change['field'] !== 'messages') {
                    continue;
                }

                $value = $change['value'];
                
                // Handle status updates
                if (isset($value['statuses'])) {
                    $this->handleStatusUpdates($value['statuses']);
                }

                // Handle messages
                if (isset($value['messages'])) {
                    foreach ($value['messages'] as $message) {
                        $this->processMessage($message, $value['contacts'][0] ?? []);
                    }
                }
            }
        }
    }

    /**
     * Process individual message
     */
    private function processMessage(array $message, array $contact): void
    {
        $messageType = $message['type'];
        $from = $message['from'];

        // Mark message as read
        $this->markMessageAsRead($message['id']);

        // Handle different message types
        if (isset($this->messageHandlers[$messageType])) {
            $handler = $this->messageHandlers[$messageType];
            $handler($message, $from);
        } else {
            Log::warning('Unhandled WhatsApp message type', ['type' => $messageType, 'from' => $from]);
        }
    }

    /**
     * Handle text messages
     */
    public function handleTextMessage(array $message, string $from): void
    {
        $text = strtolower(trim($message['text']['body']));
        
        // Handle commands and menu responses
        switch ($text) {
            case 'start':
            case 'mula':
            case 'hi':
            case 'hello':
            case 'halo':
                $this->sendWelcomeMessage($from);
                break;
            
            case 'help':
            case 'bantuan':
                $this->sendHelpMessage($from);
                break;
            
            case 'scan':
            case 'imbas':
            case '1':
                $this->sendScanInstructions($from);
                break;
            
            case 'koperasi':
            case 'senarai':
            case '2':
                $this->sendKoperasiList($from);
                break;
            
            case 'status':
            case '3':
                $this->sendStatusMessage($from);
                break;
            
            default:
                $this->sendDefaultResponse($from);
                break;
        }
    }

    /**
     * Handle document messages
     */
    public function handleDocumentMessage(array $message, string $from): void
    {
        try {
            $document = $message['document'];
            
            // Send processing message
            $this->sendTextMessage($from, "📄 Dokumen diterima! Sedang memproses...");

            // Validate file type
            $allowedTypes = ['application/pdf'];
            if (isset($document['mime_type']) && !in_array($document['mime_type'], $allowedTypes)) {
                $this->sendTextMessage($from, "❌ Format fail tidak disokong. Sila hantar PDF sahaja.");
                return;
            }

            // Download and process file
            $this->processUploadedMedia($from, $document['id'], $document['filename'] ?? 'payslip.pdf', 'document');

        } catch (\Exception $e) {
            Log::error('Error handling WhatsApp document: ' . $e->getMessage());
            $this->sendTextMessage($from, "❌ Ralat memproses dokumen. Sila cuba lagi.");
        }
    }

    /**
     * Handle image messages
     */
    public function handleImageMessage(array $message, string $from): void
    {
        try {
            $image = $message['image'];
            
            // Send processing message
            $this->sendTextMessage($from, "📷 Gambar diterima! Sedang memproses...");

            // Download and process file
            $this->processUploadedMedia($from, $image['id'], 'payslip_image.jpg', 'image');

        } catch (\Exception $e) {
            Log::error('Error handling WhatsApp image: ' . $e->getMessage());
            $this->sendTextMessage($from, "❌ Ralat memproses gambar. Sila cuba lagi.");
        }
    }

    /**
     * Process uploaded media (document or image)
     */
    private function processUploadedMedia(string $from, string $mediaId, string $fileName, string $mediaType): void
    {
        try {
            // Get media URL
            $mediaUrl = $this->getMediaUrl($mediaId);
            if (!$mediaUrl) {
                throw new \Exception('Failed to get media URL');
            }

            // Download file
            $response = Http::withToken($this->accessToken)->get($mediaUrl);
            if (!$response->successful()) {
                throw new \Exception('Failed to download media file');
            }

            // Store file
            $extension = pathinfo($fileName, PATHINFO_EXTENSION) ?: ($mediaType === 'image' ? 'jpg' : 'pdf');
            $path = 'payslips/whatsapp/' . uniqid() . '.' . $extension;
            Storage::put($path, $response->body());

            // Register user if not exists
            $user = $this->registerUserIfNotExists($from);

            // Create payslip record
            $payslip = Payslip::create([
                'user_id' => $user->id,
                'file_path' => $path,
                'status' => 'uploaded',
                'source' => 'whatsapp',
                'processing_priority' => 1,
                'whatsapp_phone' => $from,
                'extracted_data' => [
                    'whatsapp_phone' => $from,
                    'uploaded_via' => 'whatsapp_bot',
                    'check_koperasi' => true,
                    'original_filename' => $fileName,
                ],
            ]);

            Log::info("WhatsApp payslip created: {$payslip->id} for user: {$user->id}");

            $this->sendTextMessage($from, 
                "✅ Slip gaji berjaya dimuat naik!\n\n" .
                "📊 ID Pemprosesan: *{$payslip->id}*\n" .
                "🔄 Status: Sedang diproses\n\n" .
                "Anda akan menerima keputusan analisis dalam beberapa minit. Terima kasih! 🙏"
            );

            // Dispatch processing job
            ProcessPayslip::dispatch($payslip);

        } catch (\Exception $e) {
            Log::error("Error processing WhatsApp media upload: " . $e->getMessage());
            $this->sendTextMessage($from, "❌ Ralat memproses fail. Sila cuba lagi atau hubungi sokongan.");
        }
    }

    /**
     * Send welcome message with interactive buttons
     */
    public function sendWelcomeMessage(string $to): void
    {
        $user = $this->registerUserIfNotExists($to);
        
        $message = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'header' => [
                    'type' => 'text',
                    'text' => '🏦 Koperasi Bot'
                ],
                'body' => [
                    'text' => "Selamat datang ke *Koperasi WhatsApp Bot*!\n\nSaya membantu anda:\n✅ Menganalisis slip gaji\n📊 Menyemak kelayakan koperasi\n💰 Mencari koperasi terbaik\n\nPilih satu daripada pilihan di bawah untuk mula:"
                ],
                'action' => [
                    'buttons' => [
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'scan_payslip',
                                'title' => '📄 Imbas Slip Gaji'
                            ]
                        ],
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'view_koperasi',
                                'title' => '🏦 Lihat Koperasi'
                            ]
                        ],
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'check_status',
                                'title' => '📊 Semak Status'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->sendMessage($message);
    }

    /**
     * Send help message
     */
    public function sendHelpMessage(string $to): void
    {
        $text = "🆘 *Panduan Penggunaan Bot*\n\n";
        $text .= "*Arahan Utama:*\n";
        $text .= "• Hantar *start* - Mula menggunakan bot\n";
        $text .= "• Hantar *scan* - Imbas slip gaji\n";
        $text .= "• Hantar *koperasi* - Lihat senarai koperasi\n";
        $text .= "• Hantar *status* - Semak status pemprosesan\n\n";
        $text .= "*Cara Mengimbas Slip Gaji:*\n";
        $text .= "1️⃣ Hantar fail slip gaji (PDF/gambar)\n";
        $text .= "2️⃣ Tunggu analisis selesai\n";
        $text .= "3️⃣ Dapatkan laporan kelayakan koperasi\n\n";
        $text .= "*Format Fail Disokong:*\n";
        $text .= "📄 PDF (disyorkan)\n";
        $text .= "📷 JPG, PNG, JPEG\n";
        $text .= "📏 Maksimum 16MB\n\n";
        $text .= "Hantar slip gaji anda sekarang untuk analisis automatik! 🚀";

        $this->sendTextMessage($to, $text);
    }

    /**
     * Send scan instructions
     */
    public function sendScanInstructions(string $to): void
    {
        $text = "📄 *Cara Mengimbas Slip Gaji*\n\n";
        $text .= "Untuk mengimbas slip gaji anda:\n\n";
        $text .= "1️⃣ Hantar fail slip gaji dalam format:\n";
        $text .= "   • PDF (paling disyorkan)\n";
        $text .= "   • Gambar (JPG, PNG)\n\n";
        $text .= "2️⃣ Pastikan slip gaji jelas dan boleh dibaca\n\n";
        $text .= "3️⃣ Tunggu beberapa minit untuk analisis\n\n";
        $text .= "4️⃣ Terima keputusan kelayakan koperasi\n\n";
        $text .= "📤 *Hantar slip gaji anda sekarang!*";

        $this->sendTextMessage($to, $text);
    }

    /**
     * Send koperasi list
     */
    public function sendKoperasiList(string $to): void
    {
        try {
            $koperasiList = Koperasi::where('is_active', true)->orderBy('name')->get();
            
            if ($koperasiList->isEmpty()) {
                $this->sendTextMessage($to, "❌ Tiada koperasi aktif pada masa ini.");
                return;
            }

            $text = "🏦 *Senarai Koperasi Aktif*\n\n";
            foreach ($koperasiList as $index => $koperasi) {
                $text .= ($index + 1) . ". *{$koperasi->name}*\n";
                
                $rules = $koperasi->rules;
                if (isset($rules['max_peratus_gaji_bersih'])) {
                    $text .= "   📊 Max: {$rules['max_peratus_gaji_bersih']}% gaji bersih\n";
                }
                if (isset($rules['min_gaji_pokok'])) {
                    $text .= "   💰 Min gaji: RM{$rules['min_gaji_pokok']}\n";
                }
                $text .= "\n";
            }

            $text .= "📄 Hantar slip gaji untuk semak kelayakan automatik!";
            
            $this->sendTextMessage($to, $text);

        } catch (\Exception $e) {
            Log::error('Error getting koperasi list: ' . $e->getMessage());
            $this->sendTextMessage($to, "❌ Ralat mendapatkan senarai koperasi. Sila cuba lagi.");
        }
    }

    /**
     * Send status message
     */
    public function sendStatusMessage(string $to): void
    {
        try {
            $user = $this->getWhatsAppUser($to);
            if (!$user) {
                $this->sendTextMessage($to, "❌ Pengguna tidak dijumpai. Sila hantar 'start' terlebih dahulu.");
                return;
            }

            $recentPayslips = Payslip::where('user_id', $user->id)
                ->where('source', 'whatsapp')
                ->latest()
                ->take(5)
                ->get();

            if ($recentPayslips->isEmpty()) {
                $this->sendTextMessage($to, "📊 Tiada slip gaji dalam sistem. Hantar slip gaji anda untuk mula!");
                return;
            }

            $text = "📊 *Status Pemprosesan Terkini*\n\n";
            foreach ($recentPayslips as $payslip) {
                $status = $this->getStatusIcon($payslip->status);
                $text .= "ID: *{$payslip->id}* {$status}\n";
                $text .= "Tarikh: {$payslip->created_at->format('d/m/Y H:i')}\n";
                $text .= "Status: {$this->getStatusText($payslip->status)}\n\n";
            }

            $text .= "Hantar slip gaji baru untuk analisis lanjut! 📄";
            
            $this->sendTextMessage($to, $text);

        } catch (\Exception $e) {
            Log::error('Error getting status: ' . $e->getMessage());
            $this->sendTextMessage($to, "❌ Ralat mendapatkan status. Sila cuba lagi.");
        }
    }

    /**
     * Send default response for unrecognized messages
     */
    public function sendDefaultResponse(string $to): void
    {
        $text = "🤔 Saya tidak faham arahan tersebut.\n\n";
        $text .= "Sila gunakan arahan berikut:\n";
        $text .= "• *start* - Mula menggunakan bot\n";
        $text .= "• *scan* - Imbas slip gaji\n";
        $text .= "• *koperasi* - Lihat senarai koperasi\n";
        $text .= "• *status* - Semak status\n";
        $text .= "• *help* - Bantuan lengkap\n\n";
        $text .= "Atau hantar slip gaji terus untuk analisis! 📄";

        $this->sendTextMessage($to, $text);
    }

    /**
     * Send text message
     */
    public function sendTextMessage(string $to, string $text): bool
    {
        $this->validateConfiguration();
        
        $message = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'body' => $text
            ]
        ];

        return $this->sendMessage($message);
    }

    /**
     * Send message via WhatsApp API
     */
    private function sendMessage(array $message): bool
    {
        try {
            $response = Http::withToken($this->accessToken)
                ->post($this->baseApiUrl . '/messages', $message);

            if ($response->successful()) {
                Log::info('WhatsApp message sent successfully', ['to' => $message['to']]);
                return true;
            } else {
                Log::error('Failed to send WhatsApp message', [
                    'response' => $response->body(),
                    'status' => $response->status()
                ]);
                return false;
            }

        } catch (\Exception $e) {
            Log::error('Error sending WhatsApp message: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get media URL from WhatsApp
     */
    private function getMediaUrl(string $mediaId): ?string
    {
        try {
            $response = Http::withToken($this->accessToken)
                ->get("https://graph.facebook.com/v18.0/{$mediaId}");

            if ($response->successful()) {
                return $response->json('url');
            }

            Log::error('Failed to get media URL', ['media_id' => $mediaId, 'response' => $response->body()]);
            return null;

        } catch (\Exception $e) {
            Log::error('Error getting media URL: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Mark message as read
     */
    private function markMessageAsRead(string $messageId): void
    {
        try {
            Http::withToken($this->accessToken)
                ->post($this->baseApiUrl . '/messages', [
                    'messaging_product' => 'whatsapp',
                    'status' => 'read',
                    'message_id' => $messageId
                ]);
        } catch (\Exception $e) {
            Log::warning('Failed to mark message as read: ' . $e->getMessage());
        }
    }

    /**
     * Handle status updates
     */
    private function handleStatusUpdates(array $statuses): void
    {
        foreach ($statuses as $status) {
            Log::info('WhatsApp message status update', $status);
        }
    }

    /**
     * Handle interactive messages (button clicks)
     */
    public function handleInteractiveMessage(array $message, string $from): void
    {
        $interactive = $message['interactive'];
        
        if ($interactive['type'] === 'button_reply') {
            $buttonId = $interactive['button_reply']['id'];
            
            switch ($buttonId) {
                case 'scan_payslip':
                    $this->sendScanInstructions($from);
                    break;
                case 'view_koperasi':
                    $this->sendKoperasiList($from);
                    break;
                case 'check_status':
                    $this->sendStatusMessage($from);
                    break;
                default:
                    $this->sendDefaultResponse($from);
            }
        }
    }

    /**
     * Register WhatsApp user if not exists
     */
    private function registerUserIfNotExists(string $whatsappPhone): User
    {
        $email = $whatsappPhone . '@whatsapp.bot';
        
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            $user = User::create([
                'name' => 'WhatsApp User ' . substr($whatsappPhone, -4),
                'email' => $email,
                'password' => bcrypt(\Illuminate\Support\Str::random(32)),
                'role_id' => 1, // Default role
                'is_active' => true,
                'whatsapp_phone' => $whatsappPhone,
            ]);
            
            Log::info("Registered new WhatsApp user: {$user->email}");
        }
        
        return $user;
    }

    /**
     * Get user by WhatsApp phone
     */
    private function getWhatsAppUser(string $whatsappPhone): ?User
    {
        return User::where('whatsapp_phone', $whatsappPhone)->first();
    }

    /**
     * Get status icon for payslip status
     */
    private function getStatusIcon(string $status): string
    {
        return match ($status) {
            'pending' => '⏳',
            'processing' => '🔄',
            'completed' => '✅',
            'failed' => '❌',
            default => '❓'
        };
    }

    /**
     * Get status text in Malay
     */
    private function getStatusText(string $status): string
    {
        return match ($status) {
            'pending' => 'Menunggu',
            'processing' => 'Sedang diproses',
            'completed' => 'Selesai',
            'failed' => 'Gagal',
            default => 'Tidak diketahui'
        };
    }

    /**
     * Send processing result to user
     */
    public function sendProcessingResult(Payslip $payslip, array $eligibilityResults = []): void
    {
        if (!$payslip->whatsapp_phone) {
            return;
        }

        try {
            if ($payslip->status === 'completed' && !empty($eligibilityResults)) {
                // Filter to show only eligible koperasi
                $eligibleKoperasi = collect($eligibilityResults)->filter(function($result) {
                    return $result['eligible'] === true;
                })->toArray();

                if (!empty($eligibleKoperasi)) {
                    $eligibleCount = count($eligibleKoperasi);
                    $text = "🎉 *Berita Gembira! Anda layak untuk koperasi:*\n\n";
                    $text .= "📊 ID: {$payslip->id}\n";
                    $text .= "📄 Status: Berjaya diproses\n\n";
                    $text .= "🏦 *Koperasi Yang Anda Layak:*\n\n";

                    foreach ($eligibleKoperasi as $result) {
                        $text .= "✅ *{$result['koperasi_name']}*\n";
                        $text .= "   🎯 Status: Anda layak memohon!\n";
                        
                        // Show positive reasons
                        if (!empty($result['reasons'])) {
                            $mainReason = $result['reasons'][0];
                            $text .= "   💡 " . $mainReason . "\n";
                        }
                        $text .= "\n";
                    }

                    $text .= "📈 *Ringkasan:*\n";
                    $text .= "✅ Anda layak untuk {$eligibleCount} koperasi\n\n";
                    $text .= "🚀 Sedia untuk langkah seterusnya? Penasihat kewangan kami boleh membantu proses permohonan!\n\n";
                    $text .= "📞 *Hubungi Penasihat Kewangan:*\n";
                    $text .= "• Telefon: +60 12-345 6789\n";
                    $text .= "• WhatsApp: +60 12-345 6789\n";
                    $text .= "• Email: advisor@weclaim.com\n\n";
                    $text .= "💡 Nyatakan ID slip gaji anda: #{$payslip->id}\n\n";
                    $text .= "Terima kasih kerana menggunakan perkhidmatan kami! 🙏";
                } else {
                    $text = "😔 *Tiada Koperasi Yang Layak*\n\n";
                    $text .= "📊 ID: {$payslip->id}\n";
                    $text .= "📄 Status: Berjaya diproses\n\n";
                    $text .= "Malangnya, berdasarkan data slip gaji semasa, anda belum memenuhi syarat kelayakan untuk mana-mana koperasi.\n\n";
                    $text .= "💡 *Tips untuk meningkatkan kelayakan:*\n";
                    $text .= "• Semak peratus gaji bersih dalam had yang diperlukan\n";
                    $text .= "• Pastikan semua data slip gaji diekstrak dengan betul\n";
                    $text .= "• Cuba lagi bulan depan jika gaji berubah\n\n";
                    $text .= "Terima kasih! 🙏";
                }

            } elseif ($payslip->status === 'failed') {
                $text = "❌ *Ralat Memproses Slip Gaji*\n\n";
                $text .= "📊 ID: {$payslip->id}\n";
                $text .= "📄 Status: Gagal diproses\n\n";
                $text .= "Sila cuba lagi dengan slip gaji yang lebih jelas atau hubungi sokongan teknikal.\n\n";
                $text .= "Terima kasih! 🙏";
            } else {
                $text = "⚠️ Tidak dapat menyemak kelayakan koperasi. Sila semak jika data slip gaji diekstrak dengan betul.\n\n";
                $text .= "Terima kasih! 🙏";
            }

            $this->sendTextMessage($payslip->whatsapp_phone, $text);

        } catch (\Exception $e) {
            Log::error("Error sending WhatsApp processing result: " . $e->getMessage());
        }
    }

    /**
     * Verify webhook token
     */
    public function verifyWebhook(string $mode, string $token, string $challenge): ?string
    {
        if ($mode === 'subscribe' && $token === $this->webhookVerifyToken) {
            return $challenge;
        }
        return null;
    }
} 