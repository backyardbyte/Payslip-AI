# 🤖 Telegram Bot Integration Guide

## Overview

This guide provides complete instructions for integrating and using the Telegram bot with your Koperasi system. The bot allows users to:

- 📋 View all active koperasi institutions
- 🔍 Check eligibility based on salary data
- 📤 Upload and process payslip documents
- 📊 Get real-time analysis results
- 📈 View processing history

## 🚀 Quick Setup (A-Z)

### Phase 1: Environment Configuration

1. **Add Telegram configurations to `.env`:**
```bash
# Telegram Bot Configuration
TELEGRAM_BOT_TOKEN=your_bot_token_here
TELEGRAM_WEBHOOK_URL=https://yourdomain.com/api/telegram/webhook
TELEGRAM_WEBHOOK_SECRET=your_secure_random_string
```

2. **Create your bot with @BotFather on Telegram:**
   - Start a chat with [@BotFather](https://t.me/botfather)
   - Send `/newbot`
   - Choose a name: `YourCompany Koperasi Bot`
   - Choose a username: `yourcompany_koperasi_bot`
   - Copy the token to your `.env` file

### Phase 2: Setup Commands

1. **Create API token for bot user:**
```bash
php artisan tinker
```
```php
// Create a bot user account
$user = \App\Models\User::create([
    'name' => 'Telegram Bot',
    'email' => 'bot@telegram.system',
    'password' => bcrypt('secure-random-password'),
    'role_id' => 1, // Adjust based on your roles
    'is_active' => true,
]);

// Create API token with specific permissions
$token = $user->createApiToken('telegram_bot_main', [
    'koperasi.view',
    'payslip.create',
    'payslip.view',
    'payslip.update'
]);

echo "API Token: " . $token;
```

2. **Run database migrations:**
```bash
php artisan migrate
```

### Phase 3: Bot Deployment Options

#### Option A: Webhook (Recommended for Production)

1. **Set up webhook:**
```bash
php artisan telegram:bot webhook
```

2. **Configure web server (Nginx/Apache) to handle webhook:**
```nginx
location /api/telegram/webhook {
    try_files $uri $uri/ /index.php?$query_string;
}
```

#### Option B: Polling (Good for Development)

1. **Run bot with polling:**
```bash
php artisan telegram:bot run
```

### Phase 4: Test Your Bot

1. **Start a chat with your bot on Telegram**
2. **Send `/start` command**
3. **Test basic functionality:**
   - `/koperasi` - List all koperasi
   - `/check` - Check eligibility
   - Upload a payslip document

## 📱 Bot Features

### Commands Available

| Command | Description | Example |
|---------|-------------|---------|
| `/start` | Initialize bot and show main menu | `/start` |
| `/help` | Show help and usage instructions | `/help` |
| `/koperasi` | List all active koperasi institutions | `/koperasi` |
| `/check` | Check eligibility for koperasi | `/check` |
| `/upload` | Instructions for uploading payslips | `/upload` |
| `/history` | View processing history | `/history` |
| `/status [ID]` | Check processing status | `/status 123` |

### Interactive Features

#### 🏦 Koperasi Browsing
- View list of all active koperasi
- See eligibility requirements
- Compare different institutions
- Get detailed information

#### 🔍 Eligibility Checking
Users can check eligibility in two ways:

1. **Manual Entry:**
```
Gaji Bersih: 3500
Gaji Pokok: 4000
Peratus: 25
Umur: 35
```

2. **Document Upload:**
- Upload PDF or image of payslip
- Automatic OCR processing
- AI-powered data extraction
- Instant eligibility results

#### 📄 Document Processing
- Supports: PDF, JPG, PNG (max 5MB)
- Automatic text extraction
- Smart data recognition
- Real-time status updates

## 🛠️ API Integration

### Available Endpoints

All endpoints require API token authentication via `Authorization: Bearer {token}` header.

#### Koperasi Endpoints

```http
GET /api/telegram/koperasi
```
Get list of all active koperasi institutions.

```http
GET /api/telegram/koperasi/{id}
```
Get detailed information about specific koperasi.

```http
POST /api/telegram/koperasi/check-eligibility
```
Check eligibility against koperasi rules.

**Request Body:**
```json
{
  "gaji_bersih": 3500.00,
  "gaji_pokok": 4000.00,
  "peratus_gaji_bersih": 25.5,
  "umur": 35,
  "koperasi_ids": [1, 2, 3]
}
```

#### Payslip Endpoints

```http
POST /api/telegram/payslip/upload
```
Upload and process payslip document.

```http
GET /api/telegram/payslip/{id}/status
```
Get processing status of uploaded payslip.

```http
GET /api/telegram/payslip/history
```
Get user's payslip processing history.

### Authentication

The bot uses API token authentication. Each user interaction requires a valid token:

```bash
curl -H "Authorization: Bearer YOUR_API_TOKEN" \
     https://yourdomain.com/api/telegram/koperasi
```

## 🔧 Advanced Configuration

### Custom Bot Responses

You can customize bot responses by modifying `TelegramBotService.php`:

```php
// Custom welcome message
private function getWelcomeMessage(): string
{
    return "🏦 *Welcome to " . config('app.name') . " Koperasi Bot!*\n\n" .
           "Your personalized message here...";
}
```

### Adding New Commands

1. **Add command handler in `TelegramBotService::setupBot()`:**
```php
$this->client->command('newcommand', [$this, 'handleNewCommand']);
```

2. **Implement the handler method:**
```php
public function handleNewCommand(Message $message): void
{
    $chatId = $message->getChat()->getId();
    $this->bot->sendMessage($chatId, "Your new command response!");
}
```

### Integration with Existing Payslip Processing

The bot integrates seamlessly with your existing payslip processing system:

```php
// In TelegramBotController::uploadPayslip()
$payslip = Payslip::create([
    'user_id' => Auth::id(),
    'file_path' => $path,
    'status' => 'uploaded',
    'extracted_data' => [
        'telegram_user_id' => $request->telegram_user_id,
        'uploaded_via' => 'telegram_bot',
        'check_koperasi' => true,
    ],
]);

// Use existing job for processing
ProcessPayslip::dispatch($payslip);
```

## 🔒 Security Features

### API Token Management
- Secure token generation with SHA-256 hashing
- Per-user permissions control
- Token expiration support
- Rate limiting protection

### Webhook Security
- Secret token verification
- IP whitelist capability
- Request signing validation
- Comprehensive logging

### Data Protection
- Temporary file cleanup
- Secure file storage
- User data encryption
- Privacy compliance

## 📊 Monitoring and Logging

### Available Logs

All bot activities are logged for monitoring:

```bash
# View bot logs
tail -f storage/logs/laravel.log | grep "Telegram"

# View API access logs
tail -f storage/logs/laravel.log | grep "TelegramBot"
```

### Health Monitoring

Check bot health status:

```http
GET /api/telegram/webhook/health
```

Response:
```json
{
  "status": "healthy",
  "timestamp": "2025-01-21T10:30:00Z",
  "bot_configured": true
}
```

### Statistics Endpoint

```http
GET /api/telegram/stats
```

Get system statistics including:
- Total active koperasi
- Processed payslips count
- Bot usage metrics
- System health status

## 🚨 Troubleshooting

### Common Issues

#### Bot Not Responding
1. Check bot token configuration
2. Verify webhook URL is accessible
3. Check Laravel logs for errors
4. Ensure database connection is working

#### File Upload Failures
1. Check file size limits (max 5MB)
2. Verify supported formats (PDF, JPG, PNG)
3. Check storage permissions
4. Review OCR service status

#### Eligibility Check Errors
1. Verify koperasi data in database
2. Check business rules configuration
3. Validate input data format
4. Review calculation logic

#### API Authentication Issues
1. Verify API token is valid
2. Check token permissions
3. Ensure token hasn't expired
4. Review middleware configuration

### Debug Commands

```bash
# Test bot connection
php artisan telegram:bot setup

# Check webhook status
php artisan telegram:bot webhook

# View recent logs
php artisan pail --filter=telegram

# Clear cache if needed
php artisan cache:clear
```

## 📈 Performance Optimization

### Caching Strategy
- Cache koperasi data for faster responses
- Use Redis for session management
- Implement API response caching

### Queue Management
- Use queues for file processing
- Background job processing
- Scalable worker management

### Database Optimization
- Index frequently queried fields
- Optimize eligibility check queries
- Regular database maintenance

## 🔄 Updates and Maintenance

### Regular Tasks

1. **Monitor bot usage:**
```bash
php artisan telegram:stats
```

2. **Clean up old files:**
```bash
php artisan telegram:cleanup
```

3. **Update bot commands:**
```bash
php artisan telegram:bot setup
```

### Version Updates

When updating the system:
1. Backup database and configurations
2. Test in staging environment
3. Update bot commands if needed
4. Monitor for any breaking changes

---

## 🎯 Next Steps

1. **Test all functionality thoroughly**
2. **Set up monitoring and alerts**
3. **Train users on bot usage**
4. **Monitor performance metrics**
5. **Gather user feedback for improvements**

For additional support or customization, refer to the Laravel documentation and Telegram Bot API documentation. 