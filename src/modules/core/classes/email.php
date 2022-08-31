<?php
namespace Core;

use Phpr;
use Phpr\SystemException;
use Net\Email as NetEmail;
use System\EmailParams;
use Users\User as User;

/**
* Sends email messages.
* LSAPP uses this class for sending email notifications to customers and back-end users.
* @has_documentable_methods
* @see https://damanic.github.io/ls1-documentation/docs/caching_api Caching API
* @author LSAPP
* @package core.classes
*/
class Email
{
    /**
     * Sends an email message.
     * @param string $module_id Specifies an identifier of a module the email view belongs to
     * @param string $view Specifies a name of the email view file
     * @param mixed $viewData Message-specific information to be passed into a email view
     * @param string $subject Specifies a message subject
     * @param string $recipientName Specifies a name of a recipient
     * @param string $recipientEmail Specifies an email address of a recipient
     * @param mixed $attachments A list of file attachemnts in format path=>name
     */
    public static function send(
        $moduleId,
        $view,
        &$viewData,
        $subject,
        $recipientName,
        $recipientEmail,
        $recipients = array(),
        $settingsObj = null,
        $replyTo = array(),
        $attachments = array()
    ) {
        $settings = $settingsObj ?: EmailParams::get();
        if (!$settings || !$settings->isConfigured()) {
            throw new SystemException("Email system is not configured.");
        }

        /*
         * Load the view contents
         */
        $emailView = new EmailViewWrapper($moduleId, $view, $viewData);

        /*
         * Prepare the Email
         */
        $email = NetEmail::create();
        $email->setSubject($subject);
        $email->setSender($settings->sender_email, $settings->sender_name);
        if ($replyTo) {
            foreach ($replyTo as $address => $name) {
                $email->addReplyTo($address, $name);
            }
        }

        /*
         * Add Attachments
         */
        foreach ($attachments as $file_path => $file_name) {
            $email->addAttachment($file_path, $file_name);
        }

        /*
         * Add Recipients
         */
        $external_recipients = array();
        if (!count($recipients)) {
            $email->addRecipient($recipientEmail, $recipientName);
            $external_recipients[$recipientEmail] = $recipientName;
        } else {
            foreach ($recipients as $recipientName => $emailAddress) {
                if (!is_object($emailAddress)) {
                    $email->addRecipient($emailAddress, $recipientName);
                    $external_recipients[$emailAddress] = $recipientName;
                } elseif ($emailAddress instanceof User) {
                    $email->addRecipient($emailAddress->email, $emailAddress->name);
                    $external_recipients[$emailAddress->email] = $emailAddress->name;
                }
            }
        }

        /*
         * Prepare content
         */
        $custom_data = array_key_exists('custom_data', $viewData) ? $viewData['custom_data'] : null;
        $emailView->ViewData['RecipientName'] = $recipientName;
        $emailContent = $emailView->execute();
        $emailContent = str_replace('{recipient_email}', implode(', ', array_keys($external_recipients)), $emailContent);
        $emailContent = str_replace('{email_subject}', $subject, $emailContent);
        $email->setContent($emailContent);

        /*
         * FIRE core:onSendEmail EVENT
         */
        $external_sender_params = (object) [
            'content'=>$emailContent,
            'reply_to'=>$replyTo,
            'attachments'=>$attachments,
            'recipients'=>$external_recipients,
            'from'=>$settings->sender_email,
            'from_name'=>$settings->sender_name,
            'sender'=>$settings->sender_email,
            'subject'=>$subject,
            'data'=>$custom_data
        ];
        $send_result = Phpr::$events->fireEvent('core:onSendEmail', $external_sender_params);
        foreach ($send_result as $result) {
            if ($result) {
                return $send_result;
            }
        }

        /*
         * SEND EMAIL
         */
        try {
            $email = self::configureMailer($email, $settings);
            $email->send();
        } catch (\Exception $e) {
            $errorInfo = $e->getMessage(); //$Mail->ErrorInfo @TODO reinstate
            throw new SystemException('Error sending message '.$subject.': '.$errorInfo);
        }
    }
        
    public static function sendOne($moduleId, $view, &$viewData, $subject, $userId)
    {
        $result = false;
        try {
            $user = is_object($userId) ? $userId : User::create()->find($userId);
            if (!$user) {
                return;
            }
            self::send($moduleId, $view, $viewData, $subject, $user->short_name, $user->email);
            return true;
        } catch (\Exception $ex) {
        }
        return false;
    }
        
    public static function sendToList(
        $moduleId,
        $view,
        &$viewData,
        $subject,
        $recipients,
        $throw = false,
        $replyTo = array(),
        $settingsObj = null
    ) {
        try {
            if (is_array($recipients) && !count($recipients)) {
                return;
            }
            if (is_object($recipients) && !$recipients->count) {
                return;
            }
            self::send($moduleId, $view, $viewData, $subject, null, null, $recipients, $settingsObj, $replyTo);
        } catch (\Exception $ex) {
            if ($throw) {
                throw $ex;
            }
        }
    }

    protected static function configureMailer(NetEmail $email, EmailParams $settings)
    {
        switch ($settings->send_mode) {
            case EmailParams::mode_smtp:
                $port = strlen($settings->smtp_port) ? $settings->smtp_port : 25;
                $email->setModeSmtp(
                    $settings->smtp_address,
                    $port,
                    $settings->smtp_ssl,
                    $settings->smtp_user,
                    base64_decode($settings->smtp_password)
                );
                break;
            case EmailParams::mode_sendmail:
                $path = $settings->sendmail_path;
                if (!strlen($path)) {
                    $value = '/usr/sbin/sendmail';
                }
                if (substr($path, -1) == '/') {
                    $path = substr($path, 0, -1);
                }
                if (substr($path, -9) != '/sendmail') {
                    $path .= '/sendmail';
                }
                $email->setModeSendmail($path);
                break;
            default:
                $email->setModeMail();
                break;
        }

        return $email;
    }
        
    /*
     * Event descriptions
     */

    /**
     * Triggered before LSAPP sends an email message.
     * You can use this event to send email messages with third-party software or services.
     * The event handler should accept a single parameter - the object containing information about the message to be sent.
     * The object has the following fields:
     * <ul>
     * <li><em>content</em> - the message content in HTML format.</li>
     * <li><em>reply_to</em> - an array containing the reply-to address: array('sales@example.com'=>'Sales Department').</li>
     * <li><em>attachments</em> - an array containing a list of paths to the attachment files.</li>
     * <li><em>recipients</em> - an array containing a list of recipients: array('demo@example.com'=>'Demo User').</li>
     * <li><em>from</em> - "from" email address.</li>
     * <li><em>from_name</em> - "from" name.</li>
     * <li><em>sender</em> - email sender email address.</li>
     * <li><em>subject</em> - message subject.</li>
     * <li><em>data</em> - custom parameters which could be passed by the email sending code.</li>
     * </ul>
     * The event handler should return TRUE in order to prevent the default message sending way. Example event handler:
     * <pre>
     * public function subscribeEvents()
     * {
     *   Backend::$events->addEvent('core:onSendEmail', $this, 'send_email');
     * }
     *
     * public function send_email($email_info)
     * {
     *   //
     *   // Send the message using the external service
     *   //
     *
     *   ...
     *
     *   //
     *   // Return TRUE to stop LSAPP from sending the message
     *   //
     *
     *   return true;
     * }
     * </pre>
     *
     * @event core:onSendEmail
     * @package core.events
     * @author LSAPP
     * @param array $params An list of the method parameters.
     * @return boolean Return TRUE if the default message sending should be stopped. Returns FALSE otherwise.
     */
    private function event_onSendEmail($params)
    {
    }
}
