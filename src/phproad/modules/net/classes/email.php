<?php
namespace Net;

use PHPMailer\PHPMailer\PHPMailer;
use Phpr\SystemException;

/**
 * Class for handling creating outgoing socket requests
 * @package Phproad
 */
class Email
{
    protected $options;
    protected $mailer;
    protected $recipients = [];
    protected $attachments = [];

    /**
     * Constructor
     * @param array $params Parameters
     *  - set_default: optional (default true). Sets default options as in {@link set_defaults}
     */
    public function __construct(array $params = [])
    {
        $defaultParams = [
          'set_default' => true,
        ];
        $params = array_merge($defaultParams, $params);

        $this->resetMailer();

        if ($params['set_default']) {
            $this->setDefault();
        }
    }

    /**
     * Static constructor
     * @param array $params Parameters
     * @return object Net_Email
     */
    public static function create(array $params = [])
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

    public function getOption($key)
    {
        if (isset($this->options[$key])) {
            return $this->options[$key];
        }
        return null;
    }

    public function send()
    {
        $mailer = $this->mailer;
        $mailer->From = $this->getOption('sender_email');
        $mailer->FromName = $this->getOption('sender_name');
        $mailer->Sender = $this->getOption('sender_email');
        $mailer->Subject = $this->getOption('subject');
        $mailer->isHTML(true);

        $reply_to = $this->getOption('reply_to');
        if (is_array($reply_to)) {
            foreach ($reply_to as $emailAddress => $name) {
                $mailer->addReplyTo($emailAddress, $name);
            }
        }

        $attachments = $this->getAttachments();
        if (is_array($attachments)) {
            foreach ($attachments as $file_path => $file_name) {
                $mailer->addAttachment($file_path, $file_name);
            }
        }

        $mailer->clearAddresses();
        $external_recipients = array();
        $recipients = $this->getRecipients();
        foreach ($recipients as $emailAddress => $name) {
            $mailer->addAddress($emailAddress, $name);
            $external_recipients[$emailAddress] = $name;
        }

        // Basic content parsing
        $content = $this->getOption('content');
        $html_body = $content;
        $html_body = str_replace('{recipient_email}', implode(', ', array_keys($external_recipients)), $html_body);
        $text_body = trim(strip_tags(preg_replace('|\<style\s*[^\>]*\>[^\<]*\</style\>|m', '', $html_body)));

        $mailer->Body = $html_body;
        $mailer->AltBody = $text_body;


        if (!$mailer->send()) {
            throw new SystemException('Error sending message ' . $this->getOption('subject') . ': ' . $mailer->ErrorInfo);
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
        $this->attachments = array();
        return $this;
    }

    public function addAttachment($file_path, $file_name)
    {
        $this->attachments[$file_path] = $file_name;
        return $this;
    }

    public function getAttachments()
    {
        return $this->attachments;
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
        $this->options['reply_to'][$email] = $name;
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

    public function addRecipient($email, $name)
    {
        $this->recipients[$email] = $name;
        return $this;
    }

    public function addRecipients(array $recipients)
    {
        foreach ($recipients as $recipient) {
            $email = null;
            $name = null;
            if (is_object($recipient)) {
                $email = $recipient->email;
                $name = $recipient->name;
            }
            if (is_array($recipient)) {
                $email = $recipient['email'] ?? null;
                $name = $recipient['name'] ?? null;
            }
            if ($email) {
                $this->addRecipient($email, $name);
            }
        }
        return $this;
    }

    public function resetRecipients()
    {
        $this->recipients = array();
        return $this;
    }

    public function getRecipients()
    {
        return $this->recipients;
    }
}
