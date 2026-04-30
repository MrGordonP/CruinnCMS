<?php
/**
 * CruinnCMS — Mailer
 *
 * Wrapper around PHPMailer for sending emails.
 * Falls back to PHP mail() if PHPMailer is not installed.
 *
 * Usage:
 *   Mailer::send('user@example.com', 'Welcome', '<h1>Hello</h1>', 'Hello (plain text)');
 */

namespace Cruinn;

class Mailer
{
    /**
     * Send an email.
     *
     * @param string|array $to           Recipient email(s)
     * @param string       $subject      Email subject
     * @param string       $htmlBody     HTML body content
     * @param string       $textBody     Plain text fallback (optional)
     * @param array        $attachments  File paths to attach (optional)
     * @param array        $fromOverride Override From/Reply-To: ['email' => ..., 'name' => ...] (optional)
     *                                   Use when sending on behalf of an officer position or alias address.
     * @return bool True on success
     */
    public static function send(
        string|array $to,
        string $subject,
        string $htmlBody,
        string $textBody = '',
        array $attachments = [],
        array $fromOverride = []
    ): bool {
        $config = App::config('mail');

        // Try PHPMailer first
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            return self::sendWithPHPMailer($to, $subject, $htmlBody, $textBody, $attachments, $config, $fromOverride);
        }

        // Fallback to basic mail() — limited but functional
        return self::sendWithMail($to, $subject, $htmlBody, $textBody, $config, $fromOverride);
    }

    /**
     * Send using PHPMailer (preferred).
     */
    private static function sendWithPHPMailer(
        string|array $to,
        string $subject,
        string $htmlBody,
        string $textBody,
        array $attachments,
        array $config,
        array $fromOverride = []
    ): bool {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

            // Server settings
            $mail->isSMTP();
            $mail->Host       = $config['host'];
            $mail->Port       = $config['port'];
            $mail->SMTPSecure = $config['encryption'];
            $mail->SMTPAuth   = !empty($config['username']);
            $mail->Username   = $config['username'];
            $mail->Password   = $config['password'];
            $mail->CharSet    = 'UTF-8';

            // Sender — use override (e.g. officer position address) if provided
            $fromEmail = $fromOverride['email'] ?? $config['from_email'];
            $fromName  = $fromOverride['name']  ?? $config['from_name'];
            $mail->setFrom($fromEmail, $fromName);
            $mail->addReplyTo($fromEmail, $fromName);

            // Recipients
            $recipients = is_array($to) ? $to : [$to];
            foreach ($recipients as $recipient) {
                // Parse "Name <email>" format if present
                if (preg_match('/^(.+?)\s*<([^>]+)>\s*$/', $recipient, $m)) {
                    $mail->addAddress(trim($m[2]), trim($m[1], " \t\"'"));
                } else {
                    $mail->addAddress($recipient);
                }
            }

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $textBody ?: strip_tags($htmlBody);

            // Attachments
            foreach ($attachments as $filePath) {
                if (file_exists($filePath)) {
                    $mail->addAttachment($filePath);
                }
            }

            $mail->send();
            return true;

        } catch (\Throwable $e) {
            error_log('Mailer error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send using PHP's built-in mail() function (fallback).
     */
    private static function sendWithMail(
        string|array $to,
        string $subject,
        string $htmlBody,
        string $textBody,
        array $config,
        array $fromOverride = []
    ): bool {
        $recipients = is_array($to) ? implode(', ', $to) : $to;

        $fromEmail = $fromOverride['email'] ?? $config['from_email'];
        $fromName  = $fromOverride['name']  ?? $config['from_name'];

        $boundary = md5(time());
        $headers  = "From: {$fromName} <{$fromEmail}>\r\n";
        $headers .= "Reply-To: {$fromEmail}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";

        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $body .= ($textBody ?: strip_tags($htmlBody)) . "\r\n\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $body .= $htmlBody . "\r\n\r\n";
        $body .= "--{$boundary}--";

        return mail($recipients, $subject, $body, $headers);
    }
}
