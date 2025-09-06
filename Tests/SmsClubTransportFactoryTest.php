<?php

/*
 * This file is part of the Sol.parts package.
 *
 * (c) Andrii Didenko <mail@sol.parts>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SolParts\SymfonySmsClubNotifier\Tests;

use SolParts\SymfonySmsClubNotifier\SmsClubTransportFactory;
use Symfony\Component\Notifier\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Notifier\Test\IncompleteDsnTestTrait;
use Symfony\Component\Notifier\Test\MissingRequiredOptionTestTrait;

final class SmsClubTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;
    use MissingRequiredOptionTestTrait;

    public function createFactory(): SmsClubTransportFactory
    {
        return new SmsClubTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'smsclub://im.smsclub.mobi?from=sol',
            'smsclub://authToken@default?from=sol',
        ];

        yield [
            'smsclub://im.smsclub.mobi?from=Sol+Parts',
            'smsclub://authToken@default?from=Sol Parts',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'smsclub://authToken@default?from=sol'];
        yield [false, 'somethingElse://authToken@default?from=sol'];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: from' => ['smsclub://authToken@default'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://authToken@default?from=sol'];
        yield ['somethingElse://authToken@default'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield ['smsclub://default?from=sol'];
    }
}
