<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once dirname(dirname(__DIR__)) . '/vendor/autoload.php';

class MailHelper {
    private $db;
    private $config;

    public function __construct() {
        $this->db = new Database();
        // Load SMTP configuration from environment or config file
        $this->config = [
            'host' => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
            'port' => getenv('SMTP_PORT') ?: 587,
            'username' => getenv('SMTP_USER') ?: '',
            'password' => getenv('SMTP_PASS') ?: '',
            'encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls',
            'from_email' => getenv('MAIL_FROM') ?: 'noreply@studysphere.local',
            'from_name' => getenv('MAIL_FROM_NAME') ?: 'StudySphere'
        ];
    }

    /**
     * Send an email using a template
     * @param string $template_name - Unique name of the template (e.g., 'application_received')
     * @param string $to_email - Recipient email
     * @param string $to_name - Recipient name
     * @param array $placeholders - Associative array of placeholder values (e.g., ['user_name' => 'John'])
     * @return bool - True on success, false on failure
     */
    public function sendTemplate($template_name, $to_email, $to_name, $placeholders = []) {
        // Fetch template from database
        $template = $this->db->fetchOne(
            "SELECT * FROM email_templates WHERE name = ? AND is_active = 1",
            [$template_name]
        );
        if (!$template) {
            error_log("Email template '$template_name' not found or inactive");
            return false;
        }

        // Replace placeholders in subject and body
        $subject = $template['subject'];
        $body = $template['body'];

        // Add common placeholders
        $placeholders['site_name'] = 'StudySphere';
        $placeholders['current_year'] = date('Y');

        foreach ($placeholders as $key => $value) {
            $subject = str_replace('{{' . $key . '}}', $value, $subject);
            $body = str_replace('{{' . $key . '}}', $value, $body);
        }

        return $this->send($to_email, $to_name, $subject, $body);
    }

    /**
     * Send a plain email (no template)
     */
    public function send($to_email, $to_name, $subject, $body) {
        $mail = new PHPMailer(true);
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = $this->config['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->config['username'];
            $mail->Password   = $this->config['password'];
            $mail->SMTPSecure = $this->config['encryption'];
            $mail->Port       = $this->config['port'];

            // Recipients
            $mail->setFrom($this->config['from_email'], $this->config['from_name']);
            $mail->addAddress($to_email, $to_name);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags($body);

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Mail error: " . $mail->ErrorInfo);
            return false;
        }
    }
}