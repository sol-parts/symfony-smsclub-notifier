<?php

declare(strict_types=1);
/*
 * This file is part of the Sol.parts package.
 *
 * (c) Andrii Didenko <mail@sol.parts>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SolParts\SymfonySmsClubNotifier\Tests;

use SolParts\SymfonySmsClubNotifier\SmsClubTransport;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Notifier\Exception\LengthException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class SmsClubTransportTest extends TransportTestCase
{
    public static function createTransport(?HttpClientInterface $client = null): SmsClubTransport
    {
        return new SmsClubTransport('authToken', 'sender', $client ?? new MockHttpClient());
    }

    public static function toStringProvider(): iterable
    {
        yield ['smsclub://im.smsclub.mobi?from=sender', self::createTransport()];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('380931234567', 'Hello!')];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [new DummyMessage()];
    }

    public function testSuccessfulSend()
    {
        $response = new JsonMockResponse(body: [
            'success_request' => [
                'info' => [
                    '1241725993' => '380931234567',
                ],
            ],
        ], info: ['http_code' => 200]);

        $client = new MockHttpClient(static function (string $method, string $url, array $options) use ($response): ResponseInterface {
            $body = \json_decode($options['body'], true);
            self::assertSame([
                'src_addr' => 'sender',
                'phone' => ['380931234567'],
                'message' => 'Тест/Test',
            ], $body);

            return $response;
        });

        $message = new SmsMessage('380931234567', 'Тест/Test');

        $transport = self::createTransport($client);
        $sentMessage = $transport->send($message);

        self::assertInstanceOf(SentMessage::class, $sentMessage);
        self::assertSame('1241725993', $sentMessage->getMessageId());
    }

    public function testFailedSendWithPartialAccepted()
    {
        $response = new JsonMockResponse(body: [
            'success_request' => [
                'add_info' => [
                    '38093123456789' => 'Incorrect phone number',
                ],
            ],
        ], info: ['http_code' => 200]);

        $client = new MockHttpClient(static fn () => $response);

        $message = new SmsMessage('38093123456789', 'Test');

        $transport = self::createTransport($client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unable to send the SMS with SmsClub: "{"38093123456789":"Incorrect phone number"}".');

        $transport->send($message);
    }

    public function testFailedSend()
    {
        $response = new JsonMockResponse(body: [
            'success_request' => [
                'add_info' => [
                    'src_addr' => "Некоректне Альфа Ім'я",
                ],
            ],
        ], info: ['http_code' => 400]);

        $client = new MockHttpClient(static fn (): ResponseInterface => $response);

        $message = new SmsMessage('380931234567', 'Тест/Test');

        $transport = self::createTransport($client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unable to send the SMS with SmsClub: "{"src_addr":"Некоректне Альфа Ім\'я"}');

        $transport->send($message);
    }

    public function testInvalidFrom()
    {
        $this->expectException(LengthException::class);
        $this->expectExceptionMessage('The sender length of a SmsClub message must not exceed 20 characters.');

        new SmsClubTransport('authToken', 'abcdefghijklmnopqrstu', $this->createMock(HttpClientInterface::class));
    }

    public function testInvalidSubjectWithLatinSymbols()
    {
        $message = new SmsMessage('380931234567', \str_repeat('z', 1522));
        $transport = new SmsClubTransport('authToken', 'sender', $this->createMock(HttpClientInterface::class));

        $this->expectException(LengthException::class);
        $this->expectExceptionMessage('The subject length for "latin" symbols of a SmsClub message must not exceed 1521 characters.');

        $transport->send($message);
    }

    public function testInvalidSubjectWithCyrillicSymbols()
    {
        $message = new SmsMessage('380931234567', \str_repeat('z', 661) . 'Й');
        $transport = new SmsClubTransport('authToken', 'sender', $this->createMock(HttpClientInterface::class));

        $this->expectException(LengthException::class);
        $this->expectExceptionMessage('The subject length for "cyrillic" symbols of a SmsClub message must not exceed 661 characters.');

        $transport->send($message);
    }

    public function testSmsMessageWithInvalidFrom()
    {
        $transport = $this->createTransport();

        $this->expectException(LengthException::class);
        $this->expectExceptionMessage('The sender length of a SmsClub message must not exceed 20 characters.');

        $transport->send(new SmsMessage('380931234567', 'test', 'abcdefghijklmnopqrstu'));
    }
}
