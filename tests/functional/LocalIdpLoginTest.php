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

    /** The Test SP shows the SAML Response it received, as captured by the ACS of the debugsp module */
    public function testShowsReceivedSamlResponse(): void
    {
        $page = $this->loginViaSp(['idp' => self::LOCAL_IDP, 'sp' => 'third-sp'], 'jane-a1');
        $this->assertLoggedIn($page, 'third-sp', self::LOCAL_IDP);
        $response = $this->browser->samlMessages('Response')[0];

        self::assertCount(1, $page->filterXPath("//h3[normalize-space(.)='SAML Response']"));
        self::assertSame($response->value('/samlp:Response/@ID'), self::tableValue($page, 'Response', 'ID'));
        self::assertSame(self::LOCAL_IDP, self::tableValue($page, 'Response', 'Issuer'));
        self::assertSame('urn:oasis:names:tc:SAML:2.0:status:Success', self::tableValue($page, 'Response', 'StatusCode'));
        self::assertSame(self::spEntityId('third-sp'), self::tableValue($page, 'Assertion', 'Audience'));
        self::assertSame(
            $response->value('/samlp:Response/saml:Assertion/saml:AuthnStatement/@SessionIndex'),
            self::tableValue($page, 'Assertion', 'SessionIndex')
        );

        // Verbatim: exactly the XML the IdP sent
        self::assertSame($response->xml, $page->filter('#saml-xml-verbatim')->text(normalizeWhitespace: false));

        // IDs and timestamps get a background color derived from their value, the same color in the tables
        // and in the pretty-printed XML. A reference to an ID ("#ID") gets the color of the ID.
        $xmlColors = [];
        $page->filter('#saml-xml-pretty span')->each(static function ($span) use (&$xmlColors): void {
            $xmlColors[$span->text()][] = $span->attr('style');
        });
        $assertionId = $response->value('/samlp:Response/saml:Assertion/@ID');
        foreach ([
            ['Response', 'ID', [$response->value('/samlp:Response/@ID')]],
            ['Response', 'InResponseTo', [$response->value('/samlp:Response/@InResponseTo')]],
            ['Response', 'IssueInstant', [$response->value('/samlp:Response/@IssueInstant')]],
            ['Assertion', 'Assertion ID', [$assertionId, "#$assertionId"]],
            ['Assertion', 'SessionIndex', [$response->value('/samlp:Response/saml:Assertion/saml:AuthnStatement/@SessionIndex')]],
            ['Assertion', 'Conditions NotOnOrAfter', [$response->value('/samlp:Response/saml:Assertion/saml:Conditions/@NotOnOrAfter')]],
        ] as [$heading, $field, $xmlValues]) {
            $style = $page->filterXPath(
                "//h4[normalize-space(.)='$heading']/following-sibling::div[1]//tr[td[1][normalize-space(.)='$field']]/td[2]/code"
            )->attr('style');
            self::assertStringStartsWith('background-color: #', (string) $style, "$field has a color");
            foreach ($xmlValues as $xmlValue) {
                self::assertContains($style, $xmlColors[$xmlValue] ?? [], "$xmlValue has the color of $field in the XML");
            }
        }
        // Other values are not colored
        self::assertArrayNotHasKey(self::spEntityId('third-sp'), $xmlColors);

        // Shown while logged in, also on later visits
        $page = $this->browser->get(self::SP_PATH . '?sp=third-sp');
        self::assertCount(1, $page->filter('#saml-xml-verbatim'));
    }

    public function testNoSamlResponseShownWhenNotLoggedIn(): void
    {
        $page = $this->browser->get(self::SP_PATH);
        self::assertCount(0, $page->filterXPath("//h3[normalize-space(.)='SAML Response']"));

        $this->loginViaSp(['idp' => self::LOCAL_IDP], 'jane-a1');
        $this->browser->submit($this->browser->get(self::SP_PATH)->filter('form#logout button[value="logout"]')->form());
        $page = $this->browser->settle();
        self::assertCount(0, $page->filterXPath("//h3[normalize-space(.)='SAML Response']"), 'Not shown after logout');
    }

    public static function idps(): iterable
    {
        yield 'local IdP' => [self::LOCAL_IDP];
        yield 'EntraID mock IdP' => [self::ENTRA_IDP];
    }

    /** The login page offers the usernames that can log in in a datalist on the username field */
    #[DataProvider('idps')]
    public function testLoginPageOffersUsernames(string $idp): void
    {
        $page = $this->loginViaSp(['idp' => $idp], null);
        self::assertCount(1, $this->browser->filterLoginForm(), $this->browser->trail());

        $listId = $page->filter('input#username')->attr('list');
        self::assertNotEmpty($listId, 'The username field has a datalist');
        $usernames = $page->filter("datalist#$listId option")->each(static fn ($o): string => $o->attr('value'));
        foreach (['admin', 'jane-a1', 'joe-b2', 'user-f-raa', 'jane-a-nosho'] as $username) {
            self::assertContains($username, $usernames);
        }
        self::assertNotContains('cancel', $usernames, 'The special cancel account is not offered');
        // The accounts of the dev.openconext.local domain (used by the Stepup Behat tests) are not offered
        foreach (['jane-1', 'joe-5', 'joe--ra', 'joe--raa', 'user--sms', 'jane--nosho'] as $username) {
            self::assertNotContains($username, $usernames);
        }
        self::assertSame(array_unique($usernames), $usernames);
    }

    /** The accounts of the dev.openconext.local domain are not in the datalist, but can still log in */
    #[DataProvider('idps')]
    public function testHiddenDevDomainAccountCanLogIn(string $idp): void
    {
        $page = $this->loginViaSp(['idp' => $idp], 'joe--ra');

        $this->assertLoggedIn($page, 'default-sp', $idp);
        self::assertSame(['joe--ra'], self::attributes($page)['urn:mace:dir:attribute-def:uid'] ?? null);
        self::assertSame(['dev.openconext.local'], self::attributes($page)['urn:mace:terena.org:attribute-def:schacHomeOrganization'] ?? null);
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

    /** The Login button at the top of the form (next to the IdP and LOA) */
    public function testTopLoginButton(): void
    {
        $page = $this->browser->get(self::SP_PATH);
        $this->browser->submit($page->filter('#login_2')->form(['idp' => self::ENTRA_IDP, 'loa' => '2']));
        $page = $this->browser->settle('jane-a1');

        $this->assertLoggedIn($page, 'default-sp', self::ENTRA_IDP);
        self::assertSame('http://schemas.microsoft.com/claims/multipleauthn', self::labelValue($page, 'Requested LOA was:'));
    }

    public function testUpdateDirectLoginLink(): void
    {
        $page = $this->browser->get(self::SP_PATH);
        $page = $this->browser->submit($page->filter('#show_login_link')->form(['idp' => self::LOCAL_IDP, 'sp' => 'second-sp']));
        self::assertStringContainsString('sp=second-sp', $page->selectLink('Direct login link')->attr('href'));

        // Change an option and update the link
        $page = $this->browser->submit($page->filter('#update_login_link')->form(['sp' => 'fourth-sp', 'subject' => 'urn:example:subject']));
        $href = $page->selectLink('Direct login link')->attr('href');
        self::assertStringContainsString('sp=fourth-sp', $href);
        self::assertStringContainsString('subject=' . urlencode('urn:example:subject'), $href);
        self::assertStringContainsString('action=login', $href);
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
