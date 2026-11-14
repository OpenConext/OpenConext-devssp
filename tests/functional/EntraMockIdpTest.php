<?php

declare(strict_types=1);

namespace OpenConext\DevSsp\Tests\Functional;

use OpenConext\DevSsp\Tests\FunctionalTestCase;

/**
 * The EntraID mock IdP at /entra-mock/ (entra-mock-userpass authsource)
 */
final class EntraMockIdpTest extends FunctionalTestCase
{
    public function testLogin(): void
    {
        $page = $this->loginViaSp(['idp' => self::ENTRA_IDP, 'loa' => '2'], 'jane-a2');

        $this->assertLoggedIn($page, 'default-sp', self::ENTRA_IDP);
        self::assertStringStartsWith('https://ssp.dev.openconext.local/entra-mock/', $this->browser->lastAuthnRequest()->destination);
        self::assertSame(
            ['Format' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress', 'Value' => 'jane-a2@institution-a.example.com'],
            self::nameId($page)
        );
        self::assertSame('http://schemas.microsoft.com/claims/multipleauthn', self::labelValue($page, 'Requested LOA was:'));

        $attributes = self::attributes($page);
        self::assertSame(['jane-a2'], $attributes['urn:mace:dir:attribute-def:uid'] ?? null);
        self::assertSame(['http://schemas.microsoft.com/claims/multipleauthn'], $attributes['http://schemas.microsoft.com/claims/authnmethodsreferences'] ?? null);

        $response = $this->browser->samlMessages('Response')[0];
        self::assertSame(self::ENTRA_IDP, $response->value('/samlp:Response/saml:Issuer'));
    }

    public function testLoginPageIsThemedAndHasNoPasswordField(): void
    {
        $page = $this->loginViaSp(['idp' => self::ENTRA_IDP], null);

        self::assertCount(1, $this->browser->filterLoginForm(), $this->browser->trail());
        self::assertStringStartsWith('https://ssp.dev.openconext.local/entra-mock/', $this->browser->currentUrl());
        self::assertCount(1, $page->filter('link[href*="openconext.css"]'), 'OpenConext theme');
        self::assertCount(1, $page->filter('#cancel_button'));
        self::assertCount(0, $page->filter('input[type="password"]'));
    }

    public function testCancelReturnsSamlErrorToSp(): void
    {
        $page = $this->loginViaSp(['idp' => self::ENTRA_IDP], 'jane-a2', cancel: true);

        self::assertSame('Responder', self::lastPart(self::labelValue($page, 'StatusCode:')), $this->browser->trail());
        self::assertSame('AuthnFailed', self::lastPart(self::labelValue($page, 'SubStatusCode:')));
        self::assertSame('Authentication cancelled by user', self::labelValue($page, 'StatusMessage:'));
        self::assertSame(self::ENTRA_IDP, self::labelValue($page, 'Issuer:'));
        self::assertCount(0, $page->filter('form#logout'), 'Not logged in');

        // The error is shown once
        $page = $this->browser->get(self::SP_PATH);
        self::assertNull(self::labelValue($page, 'StatusCode:'));
    }

    public function testCancelAtLocalIdp(): void
    {
        $page = $this->loginViaSp(['idp' => self::LOCAL_IDP], 'jane-a1', cancel: true);
        self::assertSame('AuthnFailed', self::lastPart(self::labelValue($page, 'SubStatusCode:')), $this->browser->trail());
    }

    /** The mock IdP uses its own authsource, so a session at the local IdP is not used there */
    public function testNoSingleSignOnBetweenLocalAndMockIdp(): void
    {
        $this->loginViaSp(['idp' => self::LOCAL_IDP], 'jane-a1');
        $page = $this->loginViaSp(['idp' => self::ENTRA_IDP], 'jane-a2');

        self::assertSame(2, $this->browser->logins(), 'Mock IdP asked for the username');
        $this->assertLoggedIn($page, 'default-sp', self::ENTRA_IDP);
    }

    /** The mock IdP validates the signature of the AuthnRequest (redirect.validate) */
    public function testUnsignedAuthnRequestIsRejected(): void
    {
        $this->browser->allowServerErrors();
        $this->allowLogErrors('/signature/i');

        $page = $this->loginViaSp(['idp' => self::ENTRA_IDP, 'signing' => 'none'], 'jane-a2');

        self::assertSame(0, $this->browser->logins(), 'No login page' . $this->browser->trail());
        self::assertStringContainsStringIgnoringCase('signature', $page->text());
    }

    public function testAuthnRequestSignedWithUnknownKeyIsRejected(): void
    {
        $this->browser->allowServerErrors();
        $this->allowLogErrors('/signature/i');

        $page = $this->loginViaSp(['idp' => self::ENTRA_IDP, 'key' => 'secondary'], 'jane-a2');

        self::assertSame(0, $this->browser->logins(), 'No login page' . $this->browser->trail());
        self::assertStringContainsStringIgnoringCase('signature', $page->text());
    }

    private static function lastPart(?string $statusCode): ?string
    {
        return $statusCode === null ? null : substr($statusCode, strrpos($statusCode, ':') + 1);
    }
}
