<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplatesSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        $templates = [
            // Batch Completed Templates
            [
                'name' => 'batch_completed_in_app',
                'event_type' => 'batch_completed',
                'channel' => 'in_app',
                'subject' => 'Batch Processing Completed',
                'template' => 'Your batch "{{batch_name}}" has been processed successfully. {{successful_files}} out of {{total_files}} files were processed.',
                'variables' => ['batch_name', 'batch_id', 'total_files', 'successful_files', 'failed_files', 'processing_time'],
            ],
            [
                'name' => 'batch_completed_email',
                'event_type' => 'batch_completed',
                'channel' => 'email',
                'subject' => 'Batch Processing Completed - {{batch_name}}',
                'template' => '
                    <h2>Batch Processing Completed</h2>
                    <p>Hello {{user_name}},</p>
                    <p>Your batch processing operation has been completed successfully.</p>
                    
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                        <h3>Batch Details:</h3>
                        <ul>
                            <li><strong>Batch Name:</strong> {{batch_name}}</li>
                            <li><strong>Batch ID:</strong> {{batch_id}}</li>
                            <li><strong>Total Files:</strong> {{total_files}}</li>
                            <li><strong>Successfully Processed:</strong> {{successful_files}}</li>
                            <li><strong>Failed:</strong> {{failed_files}}</li>
                            <li><strong>Processing Time:</strong> {{processing_time}}</li>
                        </ul>
                    </div>
                    
                    <p>You can view the detailed results in your dashboard.</p>
                    <p>Best regards,<br>{{app_name}} Team</p>
                ',
                'variables' => ['batch_name', 'batch_id', 'total_files', 'successful_files', 'failed_files', 'processing_time', 'user_name', 'app_name'],
            ],

            // Batch Failed Templates
            [
                'name' => 'batch_failed_in_app',
                'event_type' => 'batch_failed',
                'channel' => 'in_app',
                'subject' => 'Batch Processing Failed',
                'template' => 'Your batch "{{batch_name}}" failed to process. Error: {{error_message}}',
                'variables' => ['batch_name', 'batch_id', 'error_message', 'processed_files', 'total_files'],
            ],
            [
                'name' => 'batch_failed_email',
                'event_type' => 'batch_failed',
                'channel' => 'email',
                'subject' => 'Batch Processing Failed - {{batch_name}}',
                'template' => '
                    <h2>Batch Processing Failed</h2>
                    <p>Hello {{user_name}},</p>
                    <p>Unfortunately, your batch processing operation has failed.</p>
                    
                    <div style="background: #fff5f5; border: 1px solid #fed7d7; padding: 20px; border-radius: 8px; margin: 20px 0;">
                        <h3>Error Details:</h3>
                        <ul>
                            <li><strong>Batch Name:</strong> {{batch_name}}</li>
                            <li><strong>Batch ID:</strong> {{batch_id}}</li>
                            <li><strong>Error Message:</strong> {{error_message}}</li>
                            <li><strong>Files Processed:</strong> {{processed_files}} out of {{total_files}}</li>
                        </ul>
                    </div>
                    
                    <p>Please check your files and try again, or contact support if the issue persists.</p>
                    <p>Best regards,<br>{{app_name}} Team</p>
                ',
                'variables' => ['batch_name', 'batch_id', 'error_message', 'processed_files', 'total_files', 'user_name', 'app_name'],
            ],

            // Payslip Processed Templates
            [
                'name' => 'payslip_processed_in_app',
                'event_type' => 'payslip_processed',
                'channel' => 'in_app',
                'subject' => 'Payslip Processed Successfully',
                'template' => 'Your payslip "{{file_name}}" has been processed successfully in {{processing_time}}.',
                'variables' => ['file_name', 'processing_time', 'extracted_data'],
            ],

            // Payslip Failed Templates
            [
                'name' => 'payslip_failed_in_app',
                'event_type' => 'payslip_failed',
                'channel' => 'in_app',
                'subject' => 'Payslip Processing Failed',
                'template' => 'Failed to process payslip "{{file_name}}". Error: {{error_message}}',
                'variables' => ['file_name', 'error_message'],
            ],
            [
                'name' => 'payslip_failed_email',
                'event_type' => 'payslip_failed',
                'channel' => 'email',
                'subject' => 'Payslip Processing Failed - {{file_name}}',
                'template' => '
                    <h2>Payslip Processing Failed</h2>
                    <p>Hello {{user_name}},</p>
                    <p>We encountered an issue while processing your payslip.</p>
                    
                    <div style="background: #fff5f5; border: 1px solid #fed7d7; padding: 20px; border-radius: 8px; margin: 20px 0;">
                        <h3>File Details:</h3>
                        <ul>
                            <li><strong>File Name:</strong> {{file_name}}</li>
                            <li><strong>Error Message:</strong> {{error_message}}</li>
                            <li><strong>Timestamp:</strong> {{timestamp}}</li>
                        </ul>
                    </div>
                    
                    <p>Please check the file format and try uploading again, or contact support for assistance.</p>
                    <p>Best regards,<br>{{app_name}} Team</p>
                ',
                'variables' => ['file_name', 'error_message', 'user_name', 'app_name', 'timestamp'],
            ],

            // System Maintenance Templates
            [
                'name' => 'system_maintenance_in_app',
                'event_type' => 'system_maintenance',
                'channel' => 'in_app',
                'subject' => 'System Maintenance Notice',
                'template' => 'System maintenance is scheduled. Please save your work and expect brief interruptions.',
                'variables' => ['maintenance_start', 'maintenance_end', 'maintenance_reason'],
            ],
            [
                'name' => 'system_maintenance_email',
                'event_type' => 'system_maintenance',
                'channel' => 'email',
                'subject' => 'Scheduled System Maintenance - {{app_name}}',
                'template' => '
                    <h2>Scheduled System Maintenance</h2>
                    <p>Hello {{user_name}},</p>
                    <p>We will be performing scheduled maintenance on our system.</p>
                    
                    <div style="background: #fffbeb; border: 1px solid #fed7aa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                        <h3>Maintenance Details:</h3>
                        <ul>
                            <li><strong>Start Time:</strong> {{maintenance_start}}</li>
                            <li><strong>End Time:</strong> {{maintenance_end}}</li>
                            <li><strong>Reason:</strong> {{maintenance_reason}}</li>
                        </ul>
                    </div>
                    
                    <p>During this time, you may experience brief service interruptions. We apologize for any inconvenience.</p>
                    <p>Best regards,<br>{{app_name}} Team</p>
                ',
                'variables' => ['maintenance_start', 'maintenance_end', 'maintenance_reason', 'user_name', 'app_name'],
            ],

            // Login Alert Templates
            [
                'name' => 'login_alert_in_app',
                'event_type' => 'login_alert',
                'channel' => 'in_app',
                'subject' => 'New Login Detected',
                'template' => 'New login detected from {{login_location}} at {{login_time}}.',
                'variables' => ['login_location', 'login_time', 'login_ip', 'login_device'],
            ],
            [
                'name' => 'login_alert_email',
                'event_type' => 'login_alert',
                'channel' => 'email',
                'subject' => 'New Login Alert - {{app_name}}',
                'template' => '
                    <h2>New Login Alert</h2>
                    <p>Hello {{user_name}},</p>
                    <p>We detected a new login to your account.</p>
                    
                    <div style="background: #f0f9ff; border: 1px solid #bae6fd; padding: 20px; border-radius: 8px; margin: 20px 0;">
                        <h3>Login Details:</h3>
                        <ul>
                            <li><strong>Time:</strong> {{login_time}}</li>
                            <li><strong>Location:</strong> {{login_location}}</li>
                            <li><strong>IP Address:</strong> {{login_ip}}</li>
                            <li><strong>Device:</strong> {{login_device}}</li>
                        </ul>
                    </div>
                    
                    <p>If this was not you, please change your password immediately and contact support.</p>
                    <p>Best regards,<br>{{app_name}} Team</p>
                ',
                'variables' => ['login_location', 'login_time', 'login_ip', 'login_device', 'user_name', 'app_name'],
            ],
        ];

        foreach ($templates as $template) {
            NotificationTemplate::updateOrCreate(
                ['name' => $template['name']],
                $template
            );
        }

        $this->command->info('Notification templates seeded successfully.');
    }
} 