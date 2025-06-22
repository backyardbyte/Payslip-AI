# Batch Processing System Documentation

## Overview

The Batch Processing System is a comprehensive enhancement to the Payslip-AI application that enables users to upload and process multiple payslip files simultaneously with advanced features like progress tracking, parallel processing, and scheduling.

## Features

### ✅ **Core Features Implemented**

1. **Batch Upload**
   - Upload 2-50 files simultaneously
   - Advanced settings (priority, parallel processing, concurrency)
   - Real-time progress tracking
   - Error handling and retry mechanisms

2. **Progress Monitoring**
   - Real-time batch progress updates
   - Individual file status tracking
   - Estimated completion times
   - Success/failure statistics

3. **Parallel Processing**
   - Configurable concurrent processing (1-10 files)
   - Intelligent queue management
   - Resource optimization

4. **Batch Management**
   - Create batches from existing queued files
   - Cancel running batches
   - Delete completed batches
   - Detailed batch analytics

5. **Scheduled Processing**
   - Cron-based batch scheduling
   - Automatic batch creation from queued files
   - Flexible scheduling rules

## Database Schema

### Tables Created

1. **`batch_operations`**
   - Tracks batch processing operations
   - Stores progress, settings, and metadata
   - Links to user and payslips

2. **`batch_schedules`**
   - Manages scheduled batch operations
   - Cron expression support
   - User-specific scheduling

3. **Enhanced `payslips` table**
   - Added `batch_id` for batch association
   - Processing priority support
   - Detailed timing information

## API Endpoints

### Batch Operations

```
GET    /api/batch/                    # List user's batches
POST   /api/batch/upload              # Upload batch of files
POST   /api/batch/create              # Create batch from existing files
GET    /api/batch/statistics          # Get batch statistics
GET    /api/batch/{batch}             # Get batch details
GET    /api/batch/{batch}/status      # Get batch status
POST   /api/batch/{batch}/cancel      # Cancel batch
DELETE /api/batch/{batch}             # Delete batch
```

### Request Examples

#### Upload Batch
```javascript
const formData = new FormData()
formData.append('files[]', file1)
formData.append('files[]', file2)
formData.append('batch_name', 'My Batch')
formData.append('settings[priority]', 'high')
formData.append('settings[max_concurrent]', '5')
formData.append('settings[parallel_processing]', 'true')

fetch('/api/batch/upload', {
    method: 'POST',
    body: formData
})
```

#### Create Batch from Existing Files
```javascript
fetch('/api/batch/create', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        payslip_ids: [1, 2, 3, 4],
        batch_name: 'Existing Files Batch',
        settings: {
            parallel_processing: true,
            max_concurrent: 3
        }
    })
})
```

## Frontend Components

### 1. BatchUploader Component
- **Location**: `resources/js/components/BatchUploader.vue`
- **Features**:
  - Drag & drop multiple files
  - Batch settings configuration
  - Real-time upload progress
  - Error handling and retry

### 2. BatchMonitor Component
- **Location**: `resources/js/components/BatchMonitor.vue`
- **Features**:
  - Real-time batch monitoring
  - Progress visualization
  - Batch management actions
  - Detailed file status

### 3. Enhanced Dashboard
- **Location**: `resources/js/pages/Dashboard.vue`
- **Features**:
  - Upload mode toggle (Single/Batch)
  - Integrated batch components
  - Unified interface

## Console Commands

### 1. Process Scheduled Batches
```bash
php artisan batch:process-scheduled [--dry-run] [--force]
```
- Processes due scheduled batch operations
- `--dry-run`: Show what would be processed
- `--force`: Process all schedules regardless of timing

### 2. Test Batch Processing
```bash
php artisan batch:test [--user-id=1] [--files=5] [--dry-run]
```
- Creates test batch for development/testing
- `--user-id`: User to create batch for
- `--files`: Number of test files to create
- `--dry-run`: Show what would be created

## Job Classes

### 1. ProcessBatch Job
- **Location**: `app/Jobs/ProcessBatch.php`
- **Features**:
  - Handles batch processing orchestration
  - Supports parallel and sequential processing
  - Progress tracking and error handling
  - Timeout and retry mechanisms

### 2. Enhanced ProcessPayslip Job
- **Location**: `app/Jobs/ProcessPayslip.php`
- **Features**:
  - Updated to support batch progress tracking
  - Automatic batch status updates
  - Enhanced error reporting

## Models

### 1. BatchOperation Model
- **Location**: `app/Models/BatchOperation.php`
- **Features**:
  - Automatic batch ID generation
  - Progress calculation methods
  - Status management
  - Relationship with payslips

### 2. BatchSchedule Model
- **Location**: `app/Models/BatchSchedule.php`
- **Features**:
  - Cron expression handling
  - Schedule validation
  - Next run calculation

### 3. Enhanced Payslip Model
- **Location**: `app/Models/Payslip.php`
- **Features**:
  - Batch relationship
  - Priority scopes
  - Processing timestamps

## Configuration

### Batch Settings

```php
// Default batch settings
$settings = [
    'parallel_processing' => true,    // Enable parallel processing
    'max_concurrent' => 5,           // Max concurrent files
    'priority' => 'normal',          // Queue priority (low/normal/high)
    'processing_priority' => 0,      // File processing priority (0-100)
];
```

### Queue Configuration

Add to `config/queue.php`:
```php
'connections' => [
    'redis' => [
        // ... existing config
        'queues' => ['high', 'batch', 'default', 'low'],
    ],
],
```

## Usage Examples

### 1. Basic Batch Upload

```javascript
// Frontend usage
const batchUploader = new BatchUploader({
    maxFiles: 20,
    settings: {
        priority: 'high',
        parallelProcessing: true,
        maxConcurrent: 5
    }
})

batchUploader.on('batchUploaded', (result) => {
    console.log('Batch uploaded:', result.batch_id)
})
```

### 2. Monitor Batch Progress

```javascript
// Poll batch status
const pollBatchStatus = async (batchId) => {
    const response = await fetch(`/api/batch/${batchId}/status`)
    const status = await response.json()
    
    console.log(`Progress: ${status.data.progress_percentage}%`)
    
    if (status.data.status === 'completed') {
        console.log('Batch completed!')
    }
}
```

### 3. Schedule Batch Processing

```php
// Create a scheduled batch
BatchSchedule::create([
    'user_id' => 1,
    'name' => 'Daily Processing',
    'cron_expression' => '0 2 * * *', // Daily at 2 AM
    'settings' => [
        'max_files' => 50,
        'min_age_hours' => 1,
        'parallel_processing' => true,
    ],
]);
```

## Performance Considerations

### 1. Concurrency Limits
- Default max concurrent: 5 files
- Adjustable per batch (1-10)
- Consider server resources

### 2. Memory Management
- Large batches may require increased memory limits
- Monitor queue worker memory usage
- Consider chunked processing for very large batches

### 3. Storage Optimization
- Regular cleanup of completed batches
- Implement file archiving for old batches
- Monitor disk space usage

## Security Features

### 1. File Validation
- MIME type checking
- File size limits (5MB per file)
- Maximum batch size (50 files)
- Virus scanning integration ready

### 2. Access Control
- Permission-based batch operations
- User isolation (users can only see their batches)
- Admin override capabilities

### 3. Rate Limiting
- Batch upload rate limiting (5 per minute)
- API endpoint protection
- Queue priority management

## Monitoring & Logging

### 1. Batch Metrics
- Processing success rates
- Average processing times
- Queue depth monitoring
- Error rate tracking

### 2. Logging
- Comprehensive batch operation logs
- Error tracking and reporting
- Performance metrics logging
- User activity tracking

## Troubleshooting

### Common Issues

1. **Batch Stuck in Processing**
   ```bash
   # Check queue workers
   php artisan queue:work --verbose
   
   # Restart failed jobs
   php artisan queue:retry all
   ```

2. **High Memory Usage**
   ```bash
   # Increase memory limit
   php artisan queue:work --memory=512
   
   # Process smaller batches
   # Reduce max_concurrent setting
   ```

3. **Slow Processing**
   ```bash
   # Check database indexes
   php artisan db:show --counts
   
   # Optimize queue configuration
   # Consider Redis for better performance
   ```

## Future Enhancements

### Planned Features

1. **Advanced Analytics**
   - Processing time predictions
   - Resource usage optimization
   - Performance benchmarking

2. **Enhanced Scheduling**
   - Conditional scheduling rules
   - Dynamic batch sizing
   - Load-based scheduling

3. **Integration Features**
   - Webhook notifications
   - External API integrations
   - Cloud storage support

4. **UI/UX Improvements**
   - Drag & drop batch creation
   - Advanced filtering options
   - Mobile-responsive design

## Support

For issues or questions regarding the batch processing system:

1. Check the logs: `storage/logs/laravel.log`
2. Monitor queue status: `php artisan queue:monitor`
3. Review batch statistics in the dashboard
4. Use debug commands for troubleshooting

## Version History

- **v1.0.0** - Initial batch processing implementation
- **v1.1.0** - Added scheduled processing
- **v1.2.0** - Enhanced UI components and monitoring

---

*Last Updated: January 21, 2025* 