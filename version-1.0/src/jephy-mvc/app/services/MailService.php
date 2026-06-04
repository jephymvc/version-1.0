<?php
namespace App\Services;
// jephy-mvc/app/services/MailService.php


use Core\BaseService;

class MailService extends BaseService
{
    /**
     * @var array Mail configuration
     */
    private $config;
    
    /**
     * @var resource SMTP connection resource
     */
    private $smtpConnection;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->loadConfiguration();
    }
    
    /**
     * Load mail configuration
     */
    private function loadConfiguration()
    {
        $this->config = [
            'host' => $this->config('mail.host', 'localhost'),
            'port' => $this->config('mail.port', 25),
            'username' => $this->config('mail.username', ''),
            'password' => $this->config('mail.password', ''),
            'encryption' => $this->config('mail.encryption', null),
            'from_email' => $this->config('mail.from_email', 'noreply@jephy-mvc.com'),
            'from_name' => $this->config('mail.from_name', 'Jephy-MVC'),
            'debug' => $this->config('app.debug', false)
        ];
    }
    
    /**
     * Send email
     */
    public function send($to, $subject, $body, $options = [])
    {
        $this->log('Sending email', ['to' => $to, 'subject' => $subject]);
        
        // Prepare email headers
        $headers = $this->prepareHeaders($options);
        
        // Prepare email body
        $message = $this->prepareBody($body, $options);
        
        // Send using appropriate method
        if ($this->config['host'] === 'sendmail') {
            return $this->sendViaSendmail($to, $subject, $message, $headers);
        } elseif ($this->config['host'] === 'mail') {
            return $this->sendViaMail($to, $subject, $message, $headers);
        } else {
            return $this->sendViaSMTP($to, $subject, $message, $headers);
        }
    }
    
    /**
     * Send email using PHP mail() function
     */
    private function sendViaMail($to, $subject, $message, $headers)
    {
        $headersString = $this->buildHeadersString($headers);
        
        return mail($to, $subject, $message, $headersString);
    }
    
    /**
     * Send email using sendmail
     */
    private function sendViaSendmail($to, $subject, $message, $headers)
    {
        $headersString = $this->buildHeadersString($headers);
        
        return mail($to, $subject, $message, $headersString);
    }
    
    /**
     * Send email via SMTP
     */
    private function sendViaSMTP($to, $subject, $message, $headers)
    {
        try {
            $this->connectSMTP();
            $this->sendSMTPCommand("MAIL FROM: <{$this->config['from_email']}>");
            $this->sendSMTPCommand("RCPT TO: <{$to}>");
            $this->sendSMTPCommand("DATA");
            $this->sendSMTPData($headers, $subject, $message);
            $this->sendSMTPCommand("QUIT");
            $this->closeSMTP();
            
            return true;
        } catch (\Exception $e) {
            $this->log('SMTP send failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Connect to SMTP server
     */
    private function connectSMTP()
    {
        $this->smtpConnection = fsockopen(
            $this->config['host'],
            $this->config['port'],
            $errno,
            $errstr,
            30
        );
        
        if (!$this->smtpConnection) {
            throw new \RuntimeException("SMTP connection failed: {$errstr}");
        }
        
        $response = fgets($this->smtpConnection);
        if (substr($response, 0, 3) != '220') {
            throw new \RuntimeException("SMTP greeting failed: {$response}");
        }
        
        // Send EHLO or HELO
        $this->sendSMTPCommand("EHLO " . gethostname());
        
        // Start TLS if encryption is enabled
        if ($this->config['encryption'] === 'tls') {
            $this->sendSMTPCommand("STARTTLS");
            stream_socket_enable_crypto($this->smtpConnection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $this->sendSMTPCommand("EHLO " . gethostname());
        }
        
        // Authenticate if credentials provided
        if (!empty($this->config['username'])) {
            $this->sendSMTPCommand("AUTH LOGIN");
            $this->sendSMTPCommand(base64_encode($this->config['username']));
            $this->sendSMTPCommand(base64_encode($this->config['password']));
        }
    }
    
    /**
     * Send SMTP command
     */
    private function sendSMTPCommand($command)
    {
        fputs($this->smtpConnection, $command . "\r\n");
        $response = fgets($this->smtpConnection);
        
        if (substr($response, 0, 3) >= 400) {
            throw new \RuntimeException("SMTP command failed: {$command} - {$response}");
        }
        
        return $response;
    }
    
    /**
     * Send SMTP data
     */
    private function sendSMTPData($headers, $subject, $message)
    {
        $data = "Subject: {$subject}\r\n";
        $data .= $this->buildHeadersString($headers);
        $data .= "\r\n";
        $data .= $message;
        $data .= "\r\n.\r\n";
        
        fputs($this->smtpConnection, $data);
        $response = fgets($this->smtpConnection);
        
        if (substr($response, 0, 3) != '250') {
            throw new \RuntimeException("SMTP data send failed: {$response}");
        }
    }
    
    /**
     * Close SMTP connection
     */
    private function closeSMTP()
    {
        if ($this->smtpConnection) {
            fclose($this->smtpConnection);
        }
    }
    
    /**
     * Prepare email headers
     */
    private function prepareHeaders($options)
    {
        $headers = [];
        
        // From header
        $fromName = $options['from_name'] ?? $this->config['from_name'];
        $fromEmail = $options['from_email'] ?? $this->config['from_email'];
        $headers['From'] = "{$fromName} <{$fromEmail}>";
        
        // Reply-To header
        if (isset($options['reply_to'])) {
            $headers['Reply-To'] = $options['reply_to'];
        }
        
        // CC header
        if (isset($options['cc'])) {
            $headers['Cc'] = $options['cc'];
        }
        
        // BCC header
        if (isset($options['bcc'])) {
            $headers['Bcc'] = $options['bcc'];
        }
        
        // Content type
        $contentType = $options['content_type'] ?? 'text/plain';
        $charset = $options['charset'] ?? 'UTF-8';
        $headers['Content-Type'] = "{$contentType}; charset={$charset}";
        
        // MIME version
        $headers['MIME-Version'] = '1.0';
        
        // X-Mailer
        $headers['X-Mailer'] = 'Jephy-MVC Mailer';
        
        // Custom headers
        if (isset($options['headers']) && is_array($options['headers'])) {
            $headers = array_merge($headers, $options['headers']);
        }
        
        return $headers;
    }
    
    /**
     * Prepare email body
     */
    private function prepareBody($body, $options)
    {
        if (isset($options['is_html']) && $options['is_html'] === true) {
            return $body;
        }
        
        // Plain text - wrap lines
        return wordwrap($body, 70, "\r\n");
    }
    
    /**
     * Build headers string
     */
    private function buildHeadersString($headers)
    {
        $headerLines = [];
        foreach ($headers as $key => $value) {
            $headerLines[] = "{$key}: {$value}";
        }
        
        return implode("\r\n", $headerLines);
    }
    
    /**
     * Send welcome email to new user
     */
    public function sendWelcomeEmail($user)
    {
        $subject = "Welcome to " . $this->config('app.name', 'Jephy-MVC');
        
        $body = "Hello {$user->name},\n\n";
        $body .= "Welcome to our application! We're excited to have you on board.\n\n";
        $body .= "Best regards,\n";
        $body .= $this->config['from_name'];
        
        return $this->send($user->email, $subject, $body);
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail($user, $resetToken)
    {
        $resetLink = $this->config('app.url', '') . "/reset-password?token={$resetToken}";
        
        $subject = "Reset Your Password";
        
        $body = "Hello {$user->name},\n\n";
        $body .= "Click the following link to reset your password:\n";
        $body .= $resetLink . "\n\n";
        $body .= "This link will expire in 1 hour.\n\n";
        $body .= "If you didn't request this, please ignore this email.\n\n";
        $body .= "Best regards,\n";
        $body .= $this->config['from_name'];
        
        return $this->send($user->email, $subject, $body);
    }
    
    /**
     * Send email verification email
     */
    public function sendVerificationEmail($user, $verificationToken)
    {
        $verifyLink = $this->config('app.url', '') . "/verify-email?token={$verificationToken}";
        
        $subject = "Verify Your Email Address";
        
        $body = "Hello {$user->name},\n\n";
        $body .= "Please click the link below to verify your email address:\n";
        $body .= $verifyLink . "\n\n";
        $body .= "Best regards,\n";
        $body .= $this->config['from_name'];
        
        return $this->send($user->email, $subject, $body);
    }
    
    /**
     * Send notification email
     */
    public function sendNotification($to, $subject, $message, $type = 'info')
    {
        $options = [
            'content_type' => 'text/html',
            'is_html' => true
        ];
        
        $html = "<!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .notification { padding: 20px; border-left: 4px solid #" . $this->getNotificationColor($type) . "; }
                .message { margin-top: 10px; }
            </style>
        </head>
        <body>
            <div class='notification'>
                <h3>{$subject}</h3>
                <div class='message'>{$message}</div>
            </div>
        </body>
        </html>";
        
        return $this->send($to, $subject, $html, $options);
    }
    
    /**
     * Get notification color based on type
     */
    private function getNotificationColor($type)
    {
        switch ($type) {
            case 'success':
                return '28a745';
            case 'error':
                return 'dc3545';
            case 'warning':
                return 'ffc107';
            default:
                return '007bff';
        }
    }
    
    /**
     * Send bulk email
     */
    public function sendBulk($recipients, $subject, $body, $options = [])
    {
        $this->log('Sending bulk email', ['count' => count($recipients)]);
        
        $successCount = 0;
        $failCount = 0;
        
        foreach ($recipients as $recipient) {
            if ($this->send($recipient, $subject, $body, $options)) {
                $successCount++;
            } else {
                $failCount++;
            }
        }
        
        return [
            'success' => $successCount,
            'failed' => $failCount,
            'total' => count($recipients)
        ];
    }
}