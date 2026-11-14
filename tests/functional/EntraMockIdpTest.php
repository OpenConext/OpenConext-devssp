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

        self::assertSame('Responder', self::lastPart(self::tableValue($page, 'SAML Error Status', 'StatusCode')), $this->browser->trail());
        self::assertSame('AuthnFailed', self::lastPart(self::tableValue($page, 'SAML Error Status', 'SubStatusCode')));
        self::assertSame('Authentication cancelled by user', self::tableValue($page, 'SAML Error Status', 'StatusMessage'));
        self::assertSame(self::ENTRA_IDP, self::tableValue($page, 'SAML Error Status', 'Issuer'));
        self::assertCount(0, $page->filter('form#logout'), 'Not logged in');

        // The XML of the response is at the end of the page, after the advanced options
        self::assertCount(1, $page->filterXPath("//h3[normalize-space(.)='Advanced options']/following::h3[normalize-space(.)='SAML Response XML']"));
        self::assertSame($this->browser->samlMessages('Response')[0]->xml, $page->filter('#saml-xml-verbatim')->text(normalizeWhitespace: false));

        // The error is shown once
        $page = $this->browser->get(self::SP_PATH);
        self::assertNull(self::tableValue($page, 'SAML Error Status', 'StatusCode'));
        self::assertCount(0, $page->filter('#saml-xml-verbatim'));
    }

    /** A failed login does not end the SP session of an earlier login: the page shows the error first, then the earlier login */
    public function testCancelWhileLoggedIn(): void
    {
        $this->loginViaSp(['idp' => self::LOCAL_IDP], 'jane-a1');
        $page = $this->loginViaSp(['idp' => self::ENTRA_IDP], 'jane-a2', cancel: true);

        // The error comes first on the page
        self::assertSame('Last login failed', trim($page->filter('h3')->first()->text()), $this->browser->trail());
        self::assertSame('AuthnFailed', self::lastPart(self::tableValue($page, 'SAML Error Status', 'SubStatusCode')));

        // Still logged in, from the login at the local IdP
        self::assertStringContainsString('You are still logged in to SP default-sp from an earlier login', $page->filter('#earlier_login')->text());
        self::assertSame('default-sp', self::labelValue($page, 'You are logged in to SP:'));
        self::assertSame(self::LOCAL_IDP, self::labelValue($page, 'IdP EntityID:'));
        self::assertCount(1, $page->filterXPath("//h3[normalize-space(.)='SP Session (default-sp) from an earlier login, still active']"));

        // Once the error is shown, the page is back to normal
        $page = $this->browser->get(self::SP_PATH);
        self::assertCount(0, $page->filter('#earlier_login'));
        self::assertCount(1, $page->filterXPath("//h3[normalize-space(.)='SP Session (default-sp)']"));
    }

    public function testCancelAtLocalIdp(): void
    {
        $page = $this->loginViaSp(['idp' => self::LOCAL_IDP], 'jane-a1', cancel: true);
        self::assertSame('AuthnFailed', self::lastPart(self::tableValue($page, 'SAML Error Status', 'SubStatusCode')), $this->browser->trail());
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
