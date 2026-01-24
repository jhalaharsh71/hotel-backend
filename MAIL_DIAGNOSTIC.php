#!/usr/bin/env php
<?php
/**
 * Mail Configuration Diagnostic Script
 * 
 * Usage: php artisan tinker
 * Then copy and paste the diagnostic code below
 * 
 * This will help identify mail configuration issues
 */

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\OtpMail;

echo "\n=== MAIL CONFIGURATION DIAGNOSTICS ===\n\n";

// 1. Check current mail driver
echo "1. Current Mail Driver:\n";
$driver = config('mail.default');
echo "   Driver: $driver\n";
$mailer = config("mail.mailers.$driver");
echo "   Configuration: " . json_encode($mailer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

// 2. Check From Address
echo "2. From Address:\n";
$from = config('mail.from');
echo "   Address: {$from['address']}\n";
echo "   Name: {$from['name']}\n\n";

// 3. Check Log Channel (if using log driver)
if ($driver === 'log') {
    echo "3. Log Configuration (using log driver):\n";
    $logChannel = config('mail.mailers.log.channel');
    echo "   Channel: " . ($logChannel ?? 'default') . "\n";
    echo "   Location: storage/logs/laravel.log\n";
    echo "   NOTE: Emails are logged as text, not sent\n\n";
}

// 4. Check SMTP Configuration (if using SMTP)
if ($driver === 'smtp') {
    echo "3. SMTP Configuration:\n";
    echo "   Host: {$mailer['host']}\n";
    echo "   Port: {$mailer['port']}\n";
    echo "   Username: {$mailer['username']}\n";
    echo "   Password: " . (strlen($mailer['password']) > 0 ? '***hidden***' : 'NOT SET') . "\n";
    echo "   Encryption: {$mailer['scheme']}\n\n";
}

// 5. Test Email Sending
echo "4. Testing Email Send (OTP Mail):\n";
try {
    Log::info('=== MAIL DIAGNOSTIC TEST START ===');
    
    $testEmail = 'test@example.com';
    $testOtp = '123456';
    $testName = 'Test User';
    
    echo "   Sending test email to: $testEmail\n";
    echo "   OTP Code: $testOtp\n";
    echo "   User Name: $testName\n";
    
    Mail::to($testEmail)->send(new OtpMail($testOtp, $testName, $testEmail));
    
    echo "   Status: ✅ SUCCESS\n";
    echo "   The email was generated successfully\n";
    
    if ($driver === 'log') {
        echo "   Check: storage/logs/laravel.log for email content\n";
    } else if ($driver === 'smtp') {
        echo "   Check: Your email inbox or service (Mailtrap, Gmail, etc.)\n";
    }
    
    Log::info('=== MAIL DIAGNOSTIC TEST END ===');
    
} catch (\Exception $e) {
    echo "   Status: ❌ FAILED\n";
    echo "   Error: {$e->getMessage()}\n";
    echo "   Class: " . get_class($e) . "\n";
    
    Log::error('Mail diagnostic test failed', [
        'exception' => $e,
        'driver' => $driver,
    ]);
}

echo "\n=== DIAGNOSTICS COMPLETE ===\n";
echo "Next steps:\n";
echo "1. If driver is 'log', check storage/logs/laravel.log for email\n";
echo "2. If driver is 'smtp', verify MAIL_* settings in .env\n";
echo "3. For SMTP issues, check username, password, and encryption\n";
echo "4. Run: php artisan config:cache\n";
echo "\n";
?>
