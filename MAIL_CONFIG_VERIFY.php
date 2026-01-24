#!/usr/bin/env php
<?php
/**
 * MAIL CONFIGURATION VERIFICATION SCRIPT
 * 
 * Run this in backend directory:
 * php artisan tinker
 * 
 * Then copy and paste the code below
 */
    use App\Mail\OtpMail;
    use Illuminate\Support\Facades\Mail;

echo "\n===== MAIL CONFIGURATION DEBUG =====\n\n";

// 1. Check current mail driver
echo "1. MAIL DRIVER CHECK:\n";
$driver = config('mail.default');
echo "   Default Driver: $driver\n";
if ($driver === 'smtp') {
    echo "   ✓ Using SMTP (correct for email sending)\n";
} elseif ($driver === 'log') {
    echo "   ✗ Using LOG (emails won't be sent, only logged)\n";
} else {
    echo "   ⚠ Using: $driver\n";
}
echo "\n";

// 2. Check SMTP configuration
if ($driver === 'smtp') {
    echo "2. SMTP CONFIGURATION:\n";
    $smtpConfig = config('mail.mailers.smtp');
    echo "   Host: " . ($smtpConfig['host'] ?? 'NOT SET') . "\n";
    echo "   Port: " . ($smtpConfig['port'] ?? 'NOT SET') . "\n";
    echo "   Username: " . ($smtpConfig['username'] ? '***SET***' : 'NOT SET') . "\n";
    echo "   Password: " . ($smtpConfig['password'] ? '***SET***' : 'NOT SET') . "\n";
    echo "   Encryption: " . ($smtpConfig['scheme'] ?? 'NOT SET') . "\n";
    echo "\n";
    
    // 3. Verify Gmail specific settings
    if (strpos($smtpConfig['host'], 'gmail') !== false) {
        echo "3. GMAIL VERIFICATION:\n";
        if ($smtpConfig['port'] == 587) {
            echo "   ✓ Port 587 (correct for TLS)\n";
        } elseif ($smtpConfig['port'] == 465) {
            echo "   ⚠ Port 465 (for SSL, not TLS)\n";
        } else {
            echo "   ✗ Port " . $smtpConfig['port'] . " (incorrect)\n";
        }
        
        if ($smtpConfig['scheme'] === 'tls') {
            echo "   ✓ TLS encryption (correct)\n";
        } else {
            echo "   ⚠ Encryption: " . ($smtpConfig['scheme'] ?? 'NOT SET') . "\n";
        }
        echo "\n";
    }
}

// 4. Check From Address
echo "4. FROM ADDRESS:\n";
$from = config('mail.from');
echo "   Address: " . ($from['address'] ?? 'NOT SET') . "\n";
echo "   Name: " . ($from['name'] ?? 'NOT SET') . "\n";
echo "\n";

// 5. Test Mail Send
echo "5. TEST EMAIL SEND:\n";
try {

    
    echo "   Preparing to send test email...\n";
    echo "   To: test@example.com\n";
    echo "   OTP: 123456\n";
    
    Mail::to('test@example.com')->send(new OtpMail('123456', 'Test User', 'test@example.com'));
    
    echo "   ✓ Email sent successfully!\n";
    echo "   Check your email inbox or SMTP service.\n";
    
} catch (\Exception $e) {
    echo "   ✗ ERROR: " . $e->getMessage() . "\n";
    echo "   Type: " . get_class($e) . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n===== END DEBUG =====\n\n";
?>
