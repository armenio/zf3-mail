<?php

namespace Armenio\Mail;

use Zend\Mail\Exception\RuntimeException;
use Zend\Mail\Message;
use Zend\Mail\Transport\Sendmail as SendmailTransport;
use Zend\Mail\Transport\Smtp as SmtpTransport;
use Zend\Mail\Transport\SmtpOptions;
use Zend\Mime\Message as MimeMessage;
use Zend\Mime\Part as MimePart;

/**
 * Class Mail
 * @package Armenio\Mail
 */
class Mail
{
    /**
     * @param array $config
     * @return bool
     */
    public static function send($config = [])
    {
        if (empty($config['from']) || empty($config['to'])) {
            return false;
        }

        if (!empty($config['smtp'])) {
            $transport = new SmtpTransport(new SmtpOptions([
                'name' => $config['smtp']['name'],
                'host' => $config['smtp']['host'],
                'port' => $config['smtp']['port'],
                'connection_class' => 'login',
                'connection_config' => [
                    'username' => $config['smtp']['username'],
                    'password' => $config['smtp']['password'],
                    'ssl' => $config['smtp']['ssl'],
                ],
            ]));
        } else {
            $param = '';
            if (!empty($config['returnPath'])) {
                $param = sprintf('-r%s', $config['returnPath']);
            }

            $transport = new SendmailTransport($param);
        }

        $message = new Message();

        if (!empty($config['charset'])) {
            $message->setEncoding($config['charset']);
        }

        foreach ($config['from'] as $email => $name) {
            $message->setFrom($email, $name);
            break;
        }

        foreach ($config['to'] as $email => $name) {
            $message->addTo($email, $name);
        }

        if (empty($config['replyTo'])) {
            $config['replyTo'] = $config['from'];
        }

        foreach ($config['replyTo'] as $email => $name) {
            $message->setReplyTo($email, $name);
            break;
        }

        if (!empty($config['subject'])) {
            $message->setSubject($config['subject']);
        }

        $htmlBody = '';

        if (!empty($config['html']) && trim($config['html']) != '') {

            $htmlBody = trim($config['html']);

        } else if (!empty($config['template']) && file_exists($config['template'])) {

            $htmlBody = file_get_contents($config['template']);

        }

        if (empty($htmlBody)) {
            $htmlBody = '
            <html>
                <body>
                    <table>
                        <tr>
                            <td>
                                <div style="font-family: \'courier new\', courier, monospace; font-size: 14px;">{$extraBody}</div>
                            </td>
                        </tr>
                    </table>
                </body>
            </html>';
        }

        // É possível que mesmo um template html use os campos de $config['fields']
        $extraBody = '';
        if (!empty($config['fields']) && is_array($config['fields']) && !empty($config['post']) && is_array($config['post'])) {
            // Cria linhas em $extraBody com placeholders de $config['fields']
            foreach ($config['fields'] as $field => $label) {
                $extraBody .= sprintf('<strong>%s:</strong> {$%s}', $label, $field) . PHP_EOL;
            }

            // Faz replace dos placeholders por valores de $config['post']
            foreach ($config['fields'] as $field => $label) {
                $value = array_key_exists($field, $config['post']) ? $config['post'][$field] : '';

                $extraBody = str_replace(sprintf('{$%s}', $field), htmlspecialchars($value, ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8'), $extraBody);

                // Deixa para substituir quando terminar o loop
                if ($field == 'extraBody') {
                    continue;
                }

                // Faz replace em $htmlBody também, pois $extraBody nem sempre estará presente
                $htmlBody = str_replace(sprintf('{$%s}', $field), htmlspecialchars($value, ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8'), $htmlBody);
            }
        }

        $htmlBody = str_replace('{$extraBody}', htmlspecialchars(nl2br($extraBody), ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8'), $htmlBody);
        $htmlBody = trim($htmlBody);

        $html = new MimePart($htmlBody);
        $html->type = 'text/html';

        $body = new MimeMessage();
        $body->setParts([$html]);

        $message->setBody($body);

        try {
            $transport->send($message);

            return true;
        } catch (RuntimeException $e) {
        }

        return false;
    }
}
