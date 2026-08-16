# Symfony SmsClub Notifier

Provides [SmsClub](https://smsclub.mobi/) integration for Symfony Notifier.
The transport supports sending SMS messages, reading the SmsClub response from
the resulting `SentMessage`, and checking the account balance.

## Installation

```bash
composer require sol-parts/symfony-smsclub-notifier
```

## Standalone usage

The transport can be used without the Symfony Full Stack Framework:

```php
use SolParts\SymfonySmsClubNotifier\SmsClubTransport;
use Symfony\Component\Notifier\Message\SmsMessage;

$transport = new SmsClubTransport('auth-token', 'InfoCenter');
$message = new SmsMessage('380931234567', 'Your order has been shipped.');

$sentMessage = $transport->send($message);

dump($sentMessage->getMessageId());
// '1241725993'

dump($sentMessage->getInfo());
// [
//     'success_request' => [
//         'info' => [
//             '1241725993' => '380931234567',
//         ],
//     ],
// ]
```

### Checking the balance

```php
$balance = $transport->balance();

dump($balance);
// '8121.1800 UAH'
```

## Symfony Full Stack Framework

Add the SmsClub DSN to your environment:

```dotenv
# .env.local
SMSCLUB_DSN=smsclub://TOKEN@default?from=FROM
```

Register the transport factory:

```yaml
# config/services.yaml
services:
    SolParts\SymfonySmsClubNotifier\SmsClubTransportFactory:
        autowire: true
        tags: ['texter.transport_factory']
```

Configure the texter transport:

```yaml
# config/packages/notifier.yaml
framework:
    notifier:
        texter_transports:
            smsclub: '%env(SMSCLUB_DSN)%'
```

Where:

- `TOKEN` is your SmsClub API token;
- `FROM` is your registered alphanumeric sender name.

URL-encode the token and sender name if they contain characters reserved in a
URI.

Inject Symfony's `TexterInterface` to send a message:

```php
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\TexterInterface;

final class OrderNotifier
{
    public function __construct(
        private readonly TexterInterface $texter,
    ) {
    }

    public function sendShippingNotice(string $phone): void
    {
        $this->texter->send(new SmsMessage(
            $phone,
            'Your order has been shipped.',
        ));
    }
}
```

## Resources

- [Symfony Notifier SMS channel](https://symfony.com/doc/current/notifier.html#sms-channel)
- [SmsClub JSON API](https://smsclub.mobi/en/api/)
