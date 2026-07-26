<?php

/*
 * This file is part of the Sol.parts package.
 *
 * (c) Andrii Didenko <mail@sol.parts>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace SolParts\SymfonySmsClubNotifier;

use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Andrii Didenko <andrii@didenko.dev>
 * @author Oleksandr Nechyporuk <oleksandr@nechyporuk.name>
 */
final class SmsClubTransport extends AbstractTransport
{
    protected const HOST = 'im.smsclub.mobi';

    public function __construct(
        #[\SensitiveParameter]
        private readonly string $authToken,
        private readonly string $from,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return \sprintf('smsclub://%s?from=%s', $this->getEndpoint(), \urlencode($this->from));
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage;
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof SmsMessage) {
            throw new UnsupportedMessageTypeException(self::class, SmsMessage::class, $message);
        }

        $from = $message->getFrom() ?: $this->from;

        $endpoint = \sprintf('https://%s/sms/send', $this->getEndpoint());
        $response = $this->httpClient()->request('POST', $endpoint, [
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
            $textError = $content['message'] ?? \json_encode($content['success_request']['add_info'] ?? ['unknown error'], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
            throw new TransportException(\sprintf('Unable to send the SMS with SmsClub: "%s".', $textError), $response);
        }

        $messageId = \array_key_first($content['success_request']['info'] ?? []);

        $sentMessage = new SentMessage($message, (string) $this, $content);
        $sentMessage->setMessageId((string) $messageId);

        return $sentMessage;
    }

    public function balance(): string
    {
        $endpoint = \sprintf('https://%s/sms/balance', $this->getEndpoint());

        $response = $this->httpClient()->request('POST', $endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->authToken,
            ],
        ]);

        try {
            $info = $response->toArray(false);
        } catch (\Exception|\Error $e) {
            throw new TransportException('SmsClub API request execution error.', $response, 0, $e);
        }

        $amount = $info['success_request']['info']['money'] ?? 'n/a';
        $currency = $info['success_request']['info']['currency'] ?? '';

        return \trim(\sprintf('%s %s', $amount, $currency));
    }

    private function httpClient(): HttpClientInterface
    {
        return $this->client ?? throw new \LogicException('SMS Club HTTP client is not initialized.');
    }
}
