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

        $message->setEncoding($config['charset']);

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

        $message->setSubject($config['subject']);

        $htmlBody = '';

        if (!empty($config['html'])) {

            $htmlBody = $config['html'];

        } elseif (!empty($config['template'])) {
            $htmlBody = file_get_contents($config['template']);

            foreach ($config['fields'] as $field => $label) {
                $htmlBody = str_replace(sprintf('{$%s}', $field), htmlspecialchars($config['post'][$field], ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8'), $htmlBody);
            }
        } else {
            $maxWidth = 0;
            foreach ($config['fields'] as $label) {
                $currentWidth = mb_strlen($label);
                if ($currentWidth > $maxWidth) {
                    $maxWidth = $currentWidth;
                }
            }

            foreach ($config['fields'] as $field => $label) {
                $widthDiff = (strlen($label) - mb_strlen($label));
                $htmlBody .= sprintf('<strong>%s:</strong> %s', str_pad($label, $maxWidth + $widthDiff, '.', STR_PAD_RIGHT), htmlspecialchars($config['post'][$field], ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8')) . PHP_EOL;
            }

            $htmlBody = '
			<html>
				<body>
					<table>
						<tr>
							<td>
								<div style="font-family: \'courier new\', courier, monospace; font-size: 14px;">' . nl2br($htmlBody) . '</div>
							</td>
						</tr>
					</table>
				</body>
			</html>';
        }

        $html = new MimePart($htmlBody);
        $html->type = 'text/html';

        $body = new MimeMessage();
        $body->setParts([$html]);


        $message->setBody($body);

        try {
            $transport->send($message);
            return true;
        } catch (RuntimeException $e) {
            return false;
        }
    }
}