<?php

namespace App\Console\Commands;

use App\Models\Payslip;
use App\Services\TelegramBotService;
use App\Services\SimpleTelegramBotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendMissingTelegramResults extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:send-missing-results 
                            {--payslip-id= : Specific payslip ID to send results for}
                            {--test-chat-id= : Send a test notification to a specific chat ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send missing Telegram results for completed payslips or send a test notification';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $payslipId = $this->option('payslip-id');
        $testChatId = $this->option('test-chat-id');
        
        // Handle test notification
        if ($testChatId) {
            $this->sendTestNotification($testChatId);
            return Command::SUCCESS;
        }
        
        if ($payslipId) {
            // Send result for specific payslip
            $payslip = Payslip::find($payslipId);
            if (!$payslip) {
                $this->error("Payslip with ID {$payslipId} not found.");
                return Command::FAILURE;
            }
            
            $this->sendTelegramResult($payslip);
        } else {
            // Send results for all completed Telegram payslips
            $payslips = Payslip::where('source', 'telegram')
                ->where('status', 'completed')
                ->whereNotNull('telegram_chat_id')
                ->whereNotNull('extracted_data')
                ->get();
                
            $this->info("Found {$payslips->count()} completed Telegram payslips to process.");
            
            foreach ($payslips as $payslip) {
                $this->sendTelegramResult($payslip);
            }
        }
        
        $this->info('Finished sending missing Telegram results.');
        return Command::SUCCESS;
    }
    
    private function sendTestNotification(string $chatId): void
    {
        try {
            $this->info("Sending test notification to chat ID: {$chatId}...");
            
            $telegramService = new SimpleTelegramBotService();
            
            // Create a dummy payslip with test data
            $testPayslip = new Payslip([
                'id' => 999,
                'telegram_chat_id' => $chatId,
                'status' => 'completed',
                'processing_completed_at' => now(),
                'extracted_data' => [
                    'nama' => 'TEST USER',
                    'no_gaji' => 'TEST123',
                    'bulan' => '01/2025',
                    'gaji_pokok' => 5000.00,
                    'gaji_bersih' => 4000.00,
                    'peratus_gaji_bersih' => 80.00,
                    'jumlah_pendapatan' => 5500.00,
                    'jumlah_potongan' => 1500.00,
                ]
            ]);
            
            // Test eligibility results
            $eligibilityResults = [
                [
                    'koperasi_name' => 'Koperasi Test 1',
                    'eligible' => true,
                    'reasons' => ['✅ Percentage requirement met (80% ≤ 90%)']
                ],
                [
                    'koperasi_name' => 'Koperasi Test 2',
                    'eligible' => false,
                    'reasons' => ['❌ Percentage too high (80% > 75%)']
                ],
                [
                    'koperasi_name' => 'Koperasi Test 3',
                    'eligible' => true,
                    'reasons' => ['✅ All requirements met']
                ]
            ];
            
            $telegramService->sendProcessingResult($testPayslip, $eligibilityResults);
            
            $this->info("✅ Test notification sent successfully!");
            Log::info("Test Telegram notification sent to chat {$chatId}");
            
        } catch (\Exception $e) {
            $this->error("❌ Failed to send test notification: " . $e->getMessage());
            Log::error("Failed to send test Telegram notification: " . $e->getMessage());
        }
    }
    
    private function sendTelegramResult(Payslip $payslip): void
    {
        try {
            $this->info("Sending result for payslip ID {$payslip->id}...");
            
            // Check if Telegram bot token is configured
            $token = config('services.telegram.bot_token');
            if (!$token) {
                $this->error("Telegram bot token not configured. Cannot send results.");
                return;
            }

            $telegramService = new SimpleTelegramBotService();
            
            // Format eligibility results from extracted data
            $eligibilityResults = [];
            if (isset($payslip->extracted_data['detailed_koperasi_results'])) {
                // Use detailed results if available
                foreach ($payslip->extracted_data['detailed_koperasi_results'] as $koperasiName => $result) {
                    $eligibilityResults[] = [
                        'koperasi_name' => $koperasiName,
                        'eligible' => $result['eligible'],
                        'reasons' => $result['reasons']
                    ];
                }
            } elseif (isset($payslip->extracted_data['koperasi_results'])) {
                // Fallback to simple results for older payslips
                foreach ($payslip->extracted_data['koperasi_results'] as $koperasiName => $isEligible) {
                    $eligibilityResults[] = [
                        'koperasi_name' => $koperasiName,
                        'eligible' => $isEligible,
                        'reason' => $isEligible ? 'Layak' : 'Tidak layak berdasarkan peratus gaji bersih'
                    ];
                }
            }

            $telegramService->sendProcessingResult($payslip, $eligibilityResults);
            
            $this->info("✅ Sent result for payslip ID {$payslip->id} to chat {$payslip->telegram_chat_id}");
            Log::info("Manually sent Telegram notification for payslip {$payslip->id}");

        } catch (\Exception $e) {
            $this->error("❌ Failed to send result for payslip ID {$payslip->id}: " . $e->getMessage());
            Log::error("Failed to manually send Telegram notification for payslip {$payslip->id}: " . $e->getMessage());
        }
    }
}
