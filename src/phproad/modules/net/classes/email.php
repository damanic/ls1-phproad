<?php

namespace Net;

require_once(PATH_SYSTEM . "/modules/net/vendor/phpmailer/PHPMailerAutoload.php");

use PHPMailer;

use Phpr\SystemException;

/**
 * Class for handling creating outgoing socket requests
 * @package Phproad
 */
class Email
{
    protected $options;
    protected $mailer;

    /**
     * Constructor
     * @param array $params Parameters
     *  - set_default: optional (default true). Sets default options as in {@link set_defaults}
     */
    public function __construct($params = array('set_default' => true))
    {
        $this->resetMailer();

        if (isset($params['set_default']) && $params['set_default']) {
            $this->setDefault();
        }
    }

    /**
     * Static constructor
     * @param array $params Parameters
     * @return object Net_Email
     */
    public static function create($params = array('set_default' => true))
    {
        return new self($params);
    }

    /**
     * Applies default Mailer options
     */
    public function setDefault()
    {
        $this->setModeMail();
        $this->resetRecipients();
        $this->resetReplyTo();
    }

    public function resetMailer()
    {
        $mail = new PHPMailer();
        $mail->Encoding = "8bit";
        $mail->CharSet = "utf-8";
        $mail->WordWrap = 0;
        $this->mailer = $mail;
    }

    /**
     * Sets multiple request options
     * @param array $options CURL options
     */
    public function setOptions($options)
    {
        foreach ($options as $key => $value) {
            $this->options[$key] = $value;
        }
        return $this;
    }

    public function send()
    {
        extract($this->options);

        $mail = $this->mailer;
        $mail->From = $sender_email;
        $mail->FromName = $sender_name;
        $mail->Sender = $sender_email;
        $mail->Subject = $subject;
        $mail->isHTML(true);

        if (isset($reply_to) && is_array($reply_to)) {
            foreach ($reply_to as $address => $name) {
                $mail->addReplyTo($address, $name);
            }
        }

        if (isset($attachments) && is_array($attachments)) {
            foreach ($attachments as $file_path => $file_name) {
                $mail->addAttachment($file_path, $file_name);
            }
        }

        $mail->clearAddresses();

        $external_recipients = array();

        foreach ($recipients as $recipient => $email) {
            if (is_object($email) && isset($email->email)) {
                $mail->addAddress($email->email, $email->name);
                $external_recipients[$email->email] = $email->name;
            } else {
                $mail->addAddress($email, $recipient);
                $external_recipients[$email] = $recipient;
            }
        }

        // Basic content parsing
        $html_body = $content;
        $html_body = str_replace('{recipient_email}', implode(', ', array_keys($external_recipients)), $html_body);
        $text_body = trim(strip_tags(preg_replace('|\<style\s*[^\>]*\>[^\<]*\</style\>|m', '', $html_body)));

        $mail->Body = $html_body;
        $mail->AltBody = $text_body;


        if (!$mail->send()) {
            throw new SystemException('Error sending message ' . $subject . ': ' . $mail->ErrorInfo);
        }
    }

    public function setSubject($subject)
    {
        $this->options['subject'] = $subject;
        return $this;
    }

    public function setContent($content)
    {
        $this->options['content'] = $content;
        return $this;
    }

    // Attachments
    // 

    public function resetAttachments()
    {
        $this->options['attachments'] = array();
        return $this;
    }

    public function addAttachment($file_path, $file_name)
    {
        $this->options['attachments'][] = array($file_path => $file_name);
        return $this;
    }

    // Sender / Reply to
    // 

    public function resetReplyTo()
    {
        $this->options['reply_to'] = array();
        return $this;
    }

    public function addReplyTo($email, $name = null)
    {
        $this->options['reply_to'][] = array($email => $name);
        return $this;
    }

    public function setSender($email, $name = null)
    {
        $this->options['sender_email'] = $email;
        $this->options['sender_name'] = $name;
        return $this;
    }

    // Send modes
    // 

    public function setModeMail()
    {
        $this->mailer->isMail();
        return $this;
    }

    public function setModeSmtp($host, $port, $secure = false, $user = null, $password = null)
    {
        $this->mailer->Port = strlen($port) ? $port : 25;
        if ($secure) {
            $this->mailer->SMTPSecure = 'ssl';
        }

        $this->mailer->Host = $host;
        if ($user !== null && $password !== null) {
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $user;
            $this->mailer->Password = $password;
        } else {
            $this->mailer->SMTPAuth = false;
        }

        $this->mailer->isSMTP();
        return $this;
    }

    public function setModeSendmail($path)
    {
        $this->mailer->isSendmail();
        $this->mailer->Sendmail = $this->fixSendmailPath($path);
        return $this;
    }

    protected function fixSendmailPath($value)
    {
        if (!strlen($value)) {
            $value = '/usr/sbin/sendmail';
        }

        if (substr($value, -1) == '/') {
            $value = substr($value, 0, -1);
        }

        if (substr($value, -9) != '/sendmail') {
            $value .= '/sendmail';
        }

        return $value;
    }


    // Recipient handling
    //

    public function addReceipient($recipient)
    {
        $this->options['recipients'][] = $recipient;
        return $this;
    }

    public function addRecipients($recipients)
    {
        if (!is_array($recipients)) {
            $this->addReceipient($recipients);
        }

        foreach ($recipients as $recipient) {
            $this->addReceipient($recipient);
        }

        return $this;
    }

    public function resetRecipients()
    {
        $this->options['recipients'] = array();
        return $this;
    }

    public function getRecipients()
    {
        return $this->options['recipients'];
    }

}