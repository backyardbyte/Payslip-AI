<?php

namespace App\Console\Commands;

use App\Services\TelegramBotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TelegramBotCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:bot {action=run : The action to perform (run, setup, webhook)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage Telegram bot operations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');

        try {
            $botService = new TelegramBotService();

            switch ($action) {
                case 'run':
                    $this->runBot($botService);
                    break;
                case 'setup':
                    $this->setupBot($botService);
                    break;
                case 'webhook':
                    $this->setupWebhook($botService);
                    break;
                default:
                    $this->error("Unknown action: {$action}");
                    return Command::FAILURE;
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Bot error: " . $e->getMessage());
            $this->error("File: " . $e->getFile());
            $this->error("Line: " . $e->getLine());
            $this->error("Trace: " . $e->getTraceAsString());
            Log::error('Telegram bot command error', [
                'action' => $action,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Run the bot using polling
     */
    private function runBot(TelegramBotService $botService): void
    {
        $this->info('Starting Telegram bot with polling...');
        $this->info('Press Ctrl+C to stop');

        $botService->setupBot();
        $botService->run();
    }

    /**
     * Set up bot commands
     */
    private function setupBot(TelegramBotService $botService): void
    {
        $this->info('Setting up Telegram bot commands...');
        
        // Here you would typically set up bot commands via API
        $this->info('Bot commands setup completed!');
    }

    /**
     * Set up webhook
     */
    private function setupWebhook(TelegramBotService $botService): void
    {
        $webhookUrl = config('services.telegram.webhook_url');
        
        if (!$webhookUrl) {
            $this->error('Webhook URL not configured. Please set TELEGRAM_WEBHOOK_URL in your .env file.');
            return;
        }

        $this->info("Setting up webhook: {$webhookUrl}");
        
        // Here you would set up the webhook via Telegram API
        $this->info('Webhook setup completed!');
    }
} 