<?php

declare(strict_types=1);

namespace OpenConext\DevSsp\Tests\Functional;

use OpenConext\DevSsp\Tests\FunctionalTestCase;
use OpenConext\DevSsp\Tests\SamlMessage;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Log in and out at the Test SP (sp.php) using the local SimpleSAMLphp IdP (example-userpass authsource)
 */
final class LocalIdpLoginTest extends FunctionalTestCase
{
    public function testLogin(): void
    {
        $page = $this->loginViaSp(['idp' => self::LOCAL_IDP], 'jane-a1');

        $this->assertLoggedIn($page, 'default-sp', self::LOCAL_IDP);
        self::assertSame(1, $this->browser->logins());
        self::assertSame(
            ['Format' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified', 'Value' => 'urn:collab:person:institution-a.example.com:jane-a1'],
            self::nameId($page)
        );
        self::assertSame('urn:oasis:names:tc:SAML:2.0:ac:classes:PasswordProtectedTransport', self::labelValue($page, 'Actual LOA is:'));

        $attributes = self::attributes($page);
        self::assertSame(['jane-a1'], $attributes['urn:mace:dir:attribute-def:uid'] ?? null);
        self::assertSame(['jane-a1@institution-a.example.com'], $attributes['urn:mace:dir:attribute-def:eduPersonPrincipalName'] ?? null);
        self::assertSame(['institution-a.example.com'], $attributes['urn:mace:terena.org:attribute-def:schacHomeOrganization'] ?? null);
        self::assertSame(['urn:collab:person:institution-a.example.com:jane-a1'], $attributes['urn:mace:surf.nl:attribute-def:internal-collabPersonId'] ?? null);

        // The response went to the ACS of the debugsp module (see the SP metadata)
        $responses = $this->browser->samlMessages('Response');
        self::assertCount(1, $responses);
        self::assertSame('https://ssp.dev.openconext.local/simplesaml/module.php/debugsp/acs/default-sp', $responses[0]->destination);
        self::assertSame('urn:oasis:names:tc:SAML:2.0:status:Success', $responses[0]->value('/samlp:Response/samlp:Status/samlp:StatusCode/@Value'));
        self::assertNotSame('', $responses[0]->value('/samlp:Response/saml:Assertion/ds:Signature/ds:SignatureValue'), 'Assertion is signed');
    }

    public function testLoginAsAdmin(): void
    {
        $page = $this->loginViaSp(['idp' => self::LOCAL_IDP], 'admin');
        $this->assertLoggedIn($page, 'default-sp', self::LOCAL_IDP);
        self::assertSame('urn:collab:person:dev.openconext.local:admin', self::nameId($page)['Value']);
    }

    public static function serviceProviders(): iterable
    {
        foreach (['default-sp', 'second-sp', 'third-sp', 'fourth-sp'] as $sp) {
            yield $sp => [$sp];
        }
    }

    #[DataProvider('serviceProviders')]
    public function testLoginWithEachServiceProvider(string $sp): void
    {
        $page = $this->loginViaSp(['idp' => self::LOCAL_IDP, 'sp' => $sp], 'jane-a1');
        $this->assertLoggedIn($page, $sp, self::LOCAL_IDP);
        self::assertSame(self::spEntityId($sp), $this->browser->lastAuthnRequest()->value('/samlp:AuthnRequest/saml:Issuer'));
    }

    public function testSingleSignOn(): void
    {
        $this->loginViaSp(['idp' => self::LOCAL_IDP], 'jane-a1');
        // Log in again: the IdP must not ask for the username again
        $page = $this->loginViaSp(['idp' => self::LOCAL_IDP], 'jane-a1');

        $this->assertLoggedIn($page, 'default-sp', self::LOCAL_IDP);
        self::assertCount(2, $this->browser->samlMessages('AuthnRequest'));
        self::assertSame(1, $this->browser->logins(), 'Logged in only once at the IdP');
    }

    public function testForceAuthnMakesIdpAskForLoginAgain(): void
    {
        $this->loginViaSp(['idp' => self::LOCAL_IDP], 'jane-a1');
        $page = $this->loginViaSp(['idp' => self::LOCAL_IDP, 'forceauthn' => true], 'jane-a2');

        self::assertSame('true', $this->browser->lastAuthnRequest()->value('/samlp:AuthnRequest/@ForceAuthn'));
        self::assertSame(2, $this->browser->logins(), 'IdP asked for the username again');
        $this->assertLoggedIn($page, 'default-sp', self::LOCAL_IDP);
        self::assertSame('urn:collab:person:institution-a.example.com:jane-a2', self::nameId($page)['Value']);
    }

    public function testLogout(): void
    {
        $this->loginViaSp(['idp' => self::LOCAL_IDP], 'jane-a1');
        $this->browser->submit($this->browser->get(self::SP_PATH)->filter('form#logout button[value="logout"]')->form());
        $page = $this->browser->settle();

        self::assertStringContainsString('Your are not logged in', $page->text(), $this->browser->trail());
        self::assertCount(0, $page->filter('form#logout'));

        // The local IdP has no SingleLogoutService in saml20-idp-remote.php, so the SP logs out locally only
        self::assertCount(0, $this->browser->samlMessages('LogoutRequest'), $this->browser->trail());

        // ... and the IdP session remains: logging in again does not ask for the username
        $page = $this->loginViaSp(['idp' => self::LOCAL_IDP], 'jane-a1');
        $this->assertLoggedIn($page, 'default-sp', self::LOCAL_IDP);
        self::assertSame(1, $this->browser->logins());
    }

    public function testLoginUsingHttpPostBinding(): void
    {
        $page = $this->loginViaSp([
            'idp' => self::LOCAL_IDP,
            'ssobinding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
        ], 'jane-a1');

        $this->assertLoggedIn($page, 'default-sp', self::LOCAL_IDP);
        $request = $this->browser->lastAuthnRequest();
        self::assertSame(SamlMessage::BINDING_POST, $request->binding);
        self::assertSame('https://ssp.dev.openconext.local/simplesaml/saml2/idp/SSOService.php', $request->destination);
        self::assertNotSame('', $request->value('/samlp:AuthnRequest/ds:Signature/ds:SignatureValue'), 'AuthnRequest is signed');
    }

    /** The SURF MFA extension for ADFS emulation: extra POST parameters, and an ACS URL with query parameters */
    public function testEmulateAdfsMfaExtension(): void
    {
        $page = $this->loginViaSp(['idp' => self::LOCAL_IDP, 'emulateadfs' => true], 'jane-a1');

        $request = $this->browser->lastAuthnRequest();
        self::assertSame(SamlMessage::BINDING_POST, $request->binding);
        self::assertSame('ADFS.SCSA', $request->parameters['AuthMethod'] ?? null);
        self::assertStringStartsWith('<EncryptedData ', $request->parameters['Context'] ?? '');
        self::assertSame(
            'https://ssp.dev.openconext.local/simplesaml/module.php/debugsp/acs/default-sp?SAMLRequest=request_that_must_be_kept&Context=context_value_that_must_be_kept',
            $request->value('/samlp:AuthnRequest/@AssertionConsumerServiceURL')
        );

        // The local IdP does not know the ACS URL with the query parameters, and uses the ACS from the SP metadata
        $this->assertLoggedIn($page, 'default-sp', self::LOCAL_IDP);
    }

    public function testRememberedFormValuesAndReset(): void
    {
        $this->loginViaSp(['idp' => self::LOCAL_IDP, 'sp' => 'third-sp', 'requesterid' => 'https://requester.example.org'], 'jane-a1');

        // The Test SP remembers the submitted login form in the session
        $page = $this->browser->get(self::SP_PATH);
        self::assertSame('https://requester.example.org', $page->filter('#requesterid')->attr('value'));
        self::assertSame('third-sp', $page->filter('select[name="sp"] option[selected]')->attr('value'));

        // Reset forgets them
        $this->browser->submit($page->filter('#login_3 ~ button[value="reset"]')->form());
        $page = $this->browser->get(self::SP_PATH);
        self::assertSame('', $page->filter('#requesterid')->attr('value'));
    }

    public function testDirectLoginLink(): void
    {
        $page = $this->browser->get(self::SP_PATH);
        $form = $page->filter('#show_login_link')->form([
            'idp' => self::LOCAL_IDP,
            'sp' => 'second-sp',
            'requesterid' => 'https://requester.example.org',
        ]);
        $page = $this->browser->submit($form);

        $link = $page->selectLink('Direct login link');
        self::assertCount(1, $link, 'Has a direct login link');
        self::assertStringContainsString('action=login', $link->attr('href'));

        // Use the link in a new browser (i.e. without the session of the Test SP)
        $browser = $this->newBrowser();
        $browser->get($link->attr('href'));
        $page = $browser->settle('jane-a1');
        self::assertSame('second-sp', self::labelValue($page, 'You are logged in to SP:'), $browser->trail());
        self::assertSame(['https://requester.example.org'], $browser->lastAuthnRequest()->values('/samlp:AuthnRequest/samlp:Scoping/samlp:RequesterID'));
    }
}
