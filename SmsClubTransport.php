<?php
/*
 * This file is part of the Sol.parts package.
 *
 * (c) Andrii Didenko <mail@sol.parts>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SolParts\SymfonySmsClubNotifier;

use Symfony\Component\Notifier\Exception\LengthException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Andrii Didenko <andrii@didenko.dev>
 * @author Oleksandr Nechyporuk <oleksandr@nechyporuk.name>
 */
final class SmsClubTransport extends AbstractTransport
{
    protected const HOST = 'im.smsclub.mobi';

    private const SUBJECT_LATIN_LIMIT = 1521;
    private const SUBJECT_CYRILLIC_LIMIT = 661;
    private const SENDER_LIMIT = 20;

    public function __construct(
        #[\SensitiveParameter] private string $authToken,
        private string $from,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        $this->assertValidFrom($from);

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return \sprintf('smsclub://%s?from=%s', $this->getEndpoint(), urlencode($this->from));
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage;
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof SmsMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, SmsMessage::class, $message);
        }

        $this->assertValidSubject($message->getSubject());

        $fromMessage = $message->getFrom();

        if ($fromMessage) {
            $this->assertValidFrom($fromMessage);
            $from = $fromMessage;
        } else {
            $from = $this->from;
        }

        $endpoint = \sprintf('https://%s/sms/send', $this->getEndpoint());
        $response = $this->client->request('POST', $endpoint, [
            'auth_bearer' => $this->authToken,
            'json' => [
                'src_addr' => $from,
                'phone' => [$message->getPhone()],
                'message' => $message->getSubject(),
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote SmsClub server.', $response, 0, $e);
        }

        try {
            $content = $response->toArray(false);
        } catch (DecodingExceptionInterface $e) {
            throw new TransportException('Could not decode body to an array.', $response, 0, $e);
        }

        if (isset($content['success_request']['add_info']) || 200 !== $statusCode) {
            $message = $content['message'] ?? \json_encode($content['success_request']['add_info'] ?? ['unknown error'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            throw new TransportException(\sprintf('Unable to send the SMS with SmsClub: "%s".', $message), $response);
        }

        $messageId = \array_key_first($content['success_request']['info'] ?? []);

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId((string) $messageId);

        return $sentMessage;
    }

    private function assertValidFrom(string $from): void
    {
        if (\mb_strlen($from, 'UTF-8') > self::SENDER_LIMIT) {
            throw new LengthException(\sprintf('The sender length of a SmsClub message must not exceed %d characters.', self::SENDER_LIMIT));
        }
    }

    private function assertValidSubject(string $subject): void
    {
        // Detect if there is at least one cyrillic symbol in the text
        if (preg_match('/\p{Cyrillic}/u', $subject)) {
            $subjectLimit = self::SUBJECT_CYRILLIC_LIMIT;
            $symbols = 'cyrillic';
        } else {
            $subjectLimit = self::SUBJECT_LATIN_LIMIT;
            $symbols = 'latin';
        }

        if (mb_strlen($subject, 'UTF-8') > $subjectLimit) {
            throw new LengthException(\sprintf('The subject length for "%s" symbols of a SmsClub message must not exceed %d characters.', $symbols, $subjectLimit));
        }
    }
}
