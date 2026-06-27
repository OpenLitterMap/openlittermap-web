<?php

namespace Tests\Feature\Email;

use App\Support\EmailAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmailValidationTest extends TestCase
{
    /**
     * Single-label / dotless domains — the undeliverable addresses that broke
     * the Update 28 send. Laravel's plain `email` rule accepts these; filter_var
     * (isSendable / the `email:filter` rule) rejects them.
     */
    #[DataProvider('singleLabelDomains')]
    public function test_rejects_single_label_domain(string $email): void
    {
        $this->assertFalse(EmailAddress::isSendable($email), "{$email} should NOT be sendable");
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function singleLabelDomains(): array
    {
        return [
            'bare numeric domain' => ['Estherbarriga@8'],
            'single letter domain' => ['6@g'],
            'word domain no tld' => ['test@test'],
            'doubled local/domain' => ['teste@teste'],
            'gmail missing dot' => ['kateliya@gmail'],
            'gmailcom run-on' => ['gladisperedo7589@gmailcom'],
            'gmailcom run-on 2' => ['nayherliespinoza@gmailcom'],
            'tld only as domain' => ['sandra.benbeniste@com'],
            'word domain' => ['avw3@wannebo'],
            'gmail missing dot 2' => ['khaffoumouaad28@gmail'],
            'name as domain' => ['jfk@jonathanfkromer'],
        ];
    }

    public function test_rejects_missing_at_or_domain(): void
    {
        $this->assertFalse(EmailAddress::isSendable('no-at-sign'));
        $this->assertFalse(EmailAddress::isSendable('trailing@'));
        $this->assertFalse(EmailAddress::isSendable('@nolocal.com'));
        $this->assertFalse(EmailAddress::isSendable(''));
    }

    public function test_accepts_valid_address(): void
    {
        $this->assertTrue(EmailAddress::isSendable('name@example.com'));
        $this->assertTrue(EmailAddress::isSendable('a@sub.domain.co.uk'));
        $this->assertTrue(EmailAddress::isSendable('b@Example.COM'));
        $this->assertTrue(EmailAddress::isSendable('first.last+tag@gmail.com'));
    }
}
