# 📱 WhatsApp Bot Integration Guide

## Overview

This guide provides complete instructions for integrating and using the WhatsApp bot with your Koperasi system. The bot allows users to:

- 📋 View all active koperasi institutions
- 🔍 Check eligibility based on salary data
- 📤 Upload and process payslip documents
- 📊 Get real-time analysis results
- 📈 View processing history

## 🚀 Quick Setup (A-Z)

### Phase 1: Meta Developer Setup

1. **Create Meta Developer Account:**
   - Go to [Meta for Developers](https://developers.facebook.com/)
   - Create or log into your account
   - Create a new app for "Business"

2. **Set up WhatsApp Business API:**
   - Add WhatsApp product to your app
   - Get your Phone Number ID from WhatsApp > API Setup
   - Generate permanent access token from WhatsApp > API Setup

3. **Configure Webhook:**
   - Go to WhatsApp > Configuration
   - Set webhook URL: `https://yourdomain.com/api/whatsapp/webhook`
   - Set verify token (create a secure random string)
   - Subscribe to `messages` webhook field

### Phase 2: Environment Configuration

1. **Add WhatsApp configurations to `.env`:**
```bash
# WhatsApp Bot Configuration
WHATSAPP_ACCESS_TOKEN=your_permanent_access_token
WHATSAPP_PHONE_NUMBER_ID=your_phone_number_id
WHATSAPP_WEBHOOK_VERIFY_TOKEN=your_secure_verify_token
WHATSAPP_WEBHOOK_URL=https://yourdomain.com/api/whatsapp/webhook
```

2. **Run database migrations:**
```bash
php artisan migrate
```

### Phase 3: Bot Setup

1. **Test bot configuration:**
```bash
php artisan whatsapp:bot setup
```

2. **Set up webhook (displays configuration info):**
```bash
php artisan whatsapp:bot webhook
```

3. **Test bot functionality:**
```bash
php artisan whatsapp:bot test
```

## 📋 User Commands

### Basic Commands
- **start** / **mula** / **hi** / **hello** - Welcome message with menu
- **help** / **bantuan** - Detailed help information
- **scan** / **imbas** - Instructions for scanning payslips
- **koperasi** / **senarai** - List all active koperasi
- **status** - Check processing status of recent payslips

### Interactive Features
- **Upload Documents** - Send PDF files directly
- **Upload Images** - Send photos of payslips (JPG, PNG)
- **Button Interactions** - Use interactive buttons for easy navigation

## 🔧 API Endpoints

### Webhook Endpoints

```http
GET /api/whatsapp/webhook
```
Webhook verification endpoint for Meta.

```http
POST /api/whatsapp/webhook
```
Webhook handler for incoming messages.

### System Endpoints

```http
GET /api/whatsapp/ping
```
Health check endpoint.

```http
GET /api/whatsapp/webhook/health
```
Webhook health status.

#### Koperasi Endpoints

```http
GET /api/whatsapp/koperasi
```
Get all active koperasi with eligibility rules.

```http
GET /api/whatsapp/koperasi/{id}
```
Get specific koperasi details.

```http
POST /api/whatsapp/koperasi/check-eligibility
```
Check eligibility for multiple koperasi:
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
POST /api/whatsapp/payslip/upload
```
Upload and process payslip document.

```http
GET /api/whatsapp/payslip/{id}/status
```
Get processing status of uploaded payslip.

```http
GET /api/whatsapp/payslip/history
```
Get user's payslip processing history.

### Authentication

The bot uses API token authentication. Each user interaction requires a valid token:

```bash
curl -H "Authorization: Bearer YOUR_API_TOKEN" \
     https://yourdomain.com/api/whatsapp/koperasi
```

## 🎯 Bot Features

### 1. Interactive Welcome Message
- Clean button interface
- Multi-language support (English/Malay)
- Contextual help system

### 2. Document Processing
- PDF document support (recommended)
- Image processing (JPG, PNG, JPEG)
- File size limit: 16MB (WhatsApp limit)
- Automatic OCR and data extraction

### 3. Koperasi Analysis
- Real-time eligibility checking
- Detailed reason reporting
- Multi-koperasi comparison
- Percentage calculations

### 4. Status Tracking
- Processing progress updates
- Historical data access
- Error reporting
- Completion notifications

### 5. User Management
- Automatic user registration
- Phone number verification
- Session management
- Preference tracking

## 🔒 Security Features

### API Token Management
- Secure token generation with SHA-256 hashing
- Per-user permissions control
- Token expiration support
- Rate limiting protection

### Webhook Security
- Verify token validation
- Request signature verification
- IP filtering capability
- Comprehensive logging

### Data Protection
- Temporary file cleanup
- Secure file storage
- User data encryption
- Privacy compliance

## 📊 Message Flow

### 1. User Onboarding
```
User sends: "start"
Bot responds: Welcome message with interactive buttons
Bot creates: User account if doesn't exist
```

### 2. Document Upload
```
User sends: PDF document
Bot responds: "Document received! Processing..."
Bot creates: Payslip record
Bot processes: OCR and eligibility check
Bot responds: Detailed results with koperasi analysis
```

### 3. Status Inquiry
```
User sends: "status"
Bot responds: List of recent payslips with processing status
User can: View detailed results for each payslip
```

## 🛠️ Advanced Configuration

### Custom Bot Responses

You can customize bot responses by modifying `WhatsAppBotService.php`:

```php
// Custom welcome message
private function getWelcomeMessage(): string
{
    return "🏦 *Welcome to " . config('app.name') . " Koperasi Bot!*\n\n" .
           "Your personalized message here...";
}
```

### Adding New Commands

1. **Add command handler in `WhatsAppBotService::setupBot()`:**
```php
// In handleTextMessage method
case 'newcommand':
    $this->handleNewCommand($from);
    break;
```

2. **Implement the handler method:**
```php
public function handleNewCommand(string $from): void
{
    $this->sendTextMessage($from, "Your new command response!");
}
```

### Integration with Existing Payslip Processing

The bot integrates seamlessly with your existing payslip processing system:

```php
// In WhatsAppBotService::processUploadedMedia()
$payslip = Payslip::create([
    'user_id' => $user->id,
    'file_path' => $path,
    'status' => 'uploaded',
    'source' => 'whatsapp',
    'extracted_data' => [
        'whatsapp_phone' => $from,
        'uploaded_via' => 'whatsapp_bot',
        'check_koperasi' => true,
    ],
]);

// Use existing job for processing
ProcessPayslip::dispatch($payslip);
```

## 📈 Monitoring and Logging

### Available Logs

All bot activities are logged for monitoring:

```bash
# View bot logs
tail -f storage/logs/laravel.log | grep "WhatsApp"

# View API access logs
tail -f storage/logs/laravel.log | grep "WhatsAppBot"
```

### Health Monitoring

Check bot health status:

```http
GET /api/whatsapp/webhook/health
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
GET /api/whatsapp/stats
```

Get system statistics including:
- Total active koperasi
- Processed payslips count
- WhatsApp bot usage metrics
- System health status

## 🚨 Troubleshooting

### Common Issues

#### Bot Not Responding
1. Check webhook URL is accessible
2. Verify webhook verify token matches
3. Check Laravel logs for errors
4. Ensure database connection is working
5. Verify Meta app webhook subscription

#### File Upload Failures
1. Check file size limits (max 16MB for WhatsApp)
2. Verify supported formats (PDF, JPG, PNG)
3. Check storage permissions
4. Review OCR service status

#### Webhook Verification Issues
1. Check webhook verify token in Meta console
2. Verify webhook URL is correct
3. Check SSL certificate validity
4. Review webhook logs

#### API Authentication Issues
1. Verify API token is valid
2. Check token permissions
3. Ensure token hasn't expired
4. Review middleware configuration

### Debug Commands

```bash
# Test bot setup
php artisan whatsapp:bot setup

# Check webhook configuration
php artisan whatsapp:bot webhook

# Send test message
php artisan whatsapp:bot test

# View recent logs
php artisan pail --filter=whatsapp

# Clear cache if needed
php artisan cache:clear
```

### Meta Developer Console Issues

1. **App Review**: Some features may require app review
2. **Rate Limits**: Check messaging rate limits
3. **Phone Number**: Verify phone number is connected
4. **Permissions**: Ensure proper permissions are granted

## 📱 Supported WhatsApp Features

### Message Types
- ✅ Text messages
- ✅ Document messages (PDF)
- ✅ Image messages (JPG, PNG)
- ✅ Interactive buttons
- ✅ Read receipts
- ❌ Voice messages (planned)
- ❌ Video messages (planned)

### Interactive Elements
- ✅ Reply buttons
- ✅ Quick replies
- ❌ List messages (planned)
- ❌ Template messages (planned)

## 🔄 Comparison with Telegram Bot

| Feature | Telegram Bot | WhatsApp Bot |
|---------|-------------|--------------|
| File Upload | ✅ Up to 20MB | ✅ Up to 16MB |
| Document Types | PDF, Images | PDF, Images |
| Interactive UI | Inline Keyboards | Reply Buttons |
| Message Format | Markdown | WhatsApp Format |
| Webhook Setup | Simple | Requires Meta App |
| Rate Limits | Generous | Moderate |
| User Base | Tech-savvy | Mainstream |

## 🚀 Production Deployment

### Server Requirements
- HTTPS enabled (required for webhooks)
- Valid SSL certificate
- Stable internet connection
- Adequate storage for file uploads

### Performance Optimization
- Redis caching for user sessions
- Queue processing for heavy tasks
- CDN for static assets
- Database indexing for quick lookups

### Scaling Considerations
- Load balancing for high traffic
- Database read replicas
- File storage optimization
- Rate limit monitoring

## 🎉 Success Metrics

Track these metrics to measure bot success:

- **User Engagement**: Daily/Monthly active users
- **Processing Success**: Successful payslip analyses
- **Response Time**: Average processing time
- **Error Rates**: Failed uploads/processing
- **User Satisfaction**: Completion rates

## 📞 Support

For issues or questions:
1. Check this documentation
2. Review application logs
3. Test with the debug commands
4. Check Meta Developer Console
5. Verify webhook configuration

## 🔮 Future Enhancements

Planned features:
- Voice message support
- Video payslip scanning
- Multi-language interface
- AI-powered chat responses
- Integration with more koperasi systems
- Advanced analytics dashboard

---

*Last updated: January 2025* 