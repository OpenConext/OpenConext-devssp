<?php

declare(strict_types=1);

namespace OpenConext\DevSsp\Tests\Functional;

use OpenConext\DevSsp\Tests\FunctionalTestCase;
use OpenConext\DevSsp\Tests\SamlMessage;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The options of the Test SP (sp.php) end up in the AuthnRequest that is sent to the IdP.
 *
 * These tests stop at the login page of the IdP and check the AuthnRequest the browser sent.
 */
final class AuthnRequestTest extends FunctionalTestCase
{
    public function testDefaultAuthnRequest(): void
    {
        $this->loginViaSp(['idp' => self::LOCAL_IDP], null);
        self::assertCount(1, $this->browser->filterLoginForm(), 'Stopped at the IdP login page' . $this->browser->trail());

        $request = $this->browser->lastAuthnRequest();
        self::assertSame(SamlMessage::BINDING_REDIRECT, $request->binding);
        self::assertSame('https://ssp.dev.openconext.local/simplesaml/saml2/idp/SSOService.php', $request->destination);
        self::assertSame(self::spEntityId('default-sp'), $request->value('/samlp:AuthnRequest/saml:Issuer'));
        // The IdP must send the response using HTTP-POST (fixed, see $GLOBALS['gSP_ProtocolBinding'] in sp.php)
        self::assertSame('urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST', $request->value('/samlp:AuthnRequest/@ProtocolBinding'));
        self::assertSame('http://www.w3.org/2001/04/xmldsig-more#rsa-sha256', $request->parameters['SigAlg'] ?? null);
        self::assertNotEmpty($request->parameters['Signature'] ?? null);
        self::assertSame('https://ssp.dev.openconext.local/simplesaml/sp.php?sp=default-sp', $request->parameters['RelayState'] ?? null);
        self::assertSame('', $request->value('/samlp:AuthnRequest/@ForceAuthn'), 'No ForceAuthn');
        self::assertSame(0, $request->xpath->query('/samlp:AuthnRequest/samlp:Scoping')->length, 'No Scoping');
        self::assertSame(0, $request->xpath->query('/samlp:AuthnRequest/samlp:Extensions')->length, 'No Extensions');
        self::assertSame(0, $request->xpath->query('/samlp:AuthnRequest/samlp:RequestedAuthnContext')->length, 'No RequestedAuthnContext');
    }

    public static function options(): iterable
    {
        yield 'LOA' => [
            ['idp' => self::ENTRA_IDP, 'loa' => '2'],
            '/samlp:AuthnRequest/samlp:RequestedAuthnContext/saml:AuthnContextClassRef',
            ['http://schemas.microsoft.com/claims/multipleauthn'],
        ];
        yield 'RequesterID' => [
            ['requesterid' => 'https://sp1.example.org', 'requesterid2' => 'https://sp2.example.org'],
            '/samlp:AuthnRequest/samlp:Scoping/samlp:RequesterID',
            ['https://sp1.example.org', 'https://sp2.example.org'],
        ];
        yield 'Scoping IDPList' => [
            // SimpleSAMLphp only accepts IdPs that it has metadata for in the IDPList
            ['scopingIDP' => self::LOCAL_IDP, 'scopingIDP2' => self::ENTRA_IDP],
            '/samlp:AuthnRequest/samlp:Scoping/samlp:IDPList/samlp:IDPEntry/@ProviderID',
            [self::LOCAL_IDP, self::ENTRA_IDP],
        ];
        yield 'Subject' => [
            ['subject' => 'urn:collab:person:institution-a.example.com:jane-a1'],
            '/samlp:AuthnRequest/saml:Subject/saml:NameID',
            ['urn:collab:person:institution-a.example.com:jane-a1'],
        ];
        yield 'NameIDPolicy' => [
            ['nameidpolicy' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent'],
            '/samlp:AuthnRequest/samlp:NameIDPolicy/@Format',
            ['urn:oasis:names:tc:SAML:2.0:nameid-format:persistent'],
        ];
        yield 'ForceAuthn' => [
            ['forceauthn' => true],
            '/samlp:AuthnRequest/@ForceAuthn',
            ['true'],
        ];
        // The debugsp module sets the AssertionConsumerServiceURL in the AuthnRequest
        yield 'AssertionConsumerServiceURL' => [
            ['acsurl' => 'https://ssp.dev.openconext.local/simplesaml/module.php/saml/sp/saml2-acs.php/default-sp'],
            '/samlp:AuthnRequest/@AssertionConsumerServiceURL',
            ['https://ssp.dev.openconext.local/simplesaml/module.php/saml/sp/saml2-acs.php/default-sp'],
        ];
        yield 'Email UserAttributes extension' => [
            ['email_extension' => 'jane@example.org'],
            "/samlp:AuthnRequest/samlp:Extensions/gssp:UserAttributes/saml:Attribute[@Name='urn:mace:dir:attribute-def:mail']/saml:AttributeValue",
            ['jane@example.org'],
        ];
        yield 'SHO UserAttributes extension' => [
            ['sho_extension' => 'institution-a.example.com'],
            "/samlp:AuthnRequest/samlp:Extensions/gssp:UserAttributes/saml:Attribute[@Name='urn:mace:terena.org:attribute-def:schacHomeOrganization']/saml:AttributeValue",
            ['institution-a.example.com'],
        ];
        yield 'mdui:DisplayName extension' => [
            ['mdui_displayname' => 'My Service'],
            '/samlp:AuthnRequest/samlp:Extensions/mdui:UIInfo/mdui:DisplayName',
            ['My Service'],
        ];
        yield 'Second SP' => [
            ['sp' => 'fourth-sp'],
            '/samlp:AuthnRequest/saml:Issuer',
            [self::spEntityId('fourth-sp')],
        ];
    }

    /**
     * @param array<string, string|bool> $fields
     * @param list<string> $expected
     */
    #[DataProvider('options')]
    public function testOptionInAuthnRequest(array $fields, string $xpath, array $expected): void
    {
        $this->loginViaSp($fields + ['idp' => self::LOCAL_IDP], null);
        self::assertCount(1, $this->browser->filterLoginForm(), 'IdP accepted the AuthnRequest' . $this->browser->trail());
        self::assertSame($expected, $this->browser->lastAuthnRequest()->values($xpath), $this->browser->lastAuthnRequest()->xml);
    }

    /** The Request LOA option maps to an AuthnContextClassRef per IdP (see sp-config.inc) */
    public static function loas(): iterable
    {
        yield 'gateway LOA 1' => [self::GATEWAY_IDP, '1', ['http://dev.openconext.local/assurance/loa1']];
        yield 'gateway LOA 1.5' => [self::GATEWAY_IDP, '1.5', ['http://dev.openconext.local/assurance/loa1.5']];
        yield 'gateway LOA 2' => [self::GATEWAY_IDP, '2', ['http://dev.openconext.local/assurance/loa2']];
        yield 'gateway LOA 3' => [self::GATEWAY_IDP, '3', ['http://dev.openconext.local/assurance/loa3']];
        yield 'gateway SFO LOA 1.5' => [self::GATEWAY_SFO_IDP, '1.5', ['http://dev.openconext.local/assurance/sfo-level1.5']];
        yield 'gateway SFO LOA 2' => [self::GATEWAY_SFO_IDP, '2', ['http://dev.openconext.local/assurance/sfo-level2']];
        yield 'gateway SFO LOA 3' => [self::GATEWAY_SFO_IDP, '3', ['http://dev.openconext.local/assurance/sfo-level3']];
        yield 'gateway no LOA' => [self::GATEWAY_IDP, '', []];
        yield 'EntraID mock LOA 3' => [self::ENTRA_IDP, '3', ['http://schemas.microsoft.com/claims/multipleauthn']];
        // The local IdP has no LOA values: no AuthnContextClassRef is requested
        yield 'local IdP LOA 2' => [self::LOCAL_IDP, '2', []];
    }

    /** @param list<string> $expected */
    #[DataProvider('loas')]
    public function testLoa(string $idp, string $loa, array $expected): void
    {
        $this->loginViaSp(['idp' => $idp, 'loa' => $loa], null);
        $request = $this->browser->lastAuthnRequest();
        self::assertSame($expected, $request->values('/samlp:AuthnRequest/samlp:RequestedAuthnContext/saml:AuthnContextClassRef'), $request->xml);
    }

    public static function nameIdPolicies(): iterable
    {
        foreach ([
            'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified',
            'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent',
            'urn:oasis:names:tc:SAML:2.0:nameid-format:transient',
        ] as $format) {
            yield substr($format, strrpos($format, ':') + 1) => [$format];
        }
    }

    #[DataProvider('nameIdPolicies')]
    public function testNameIdPolicy(string $format): void
    {
        $this->loginViaSp(['idp' => self::LOCAL_IDP, 'nameidpolicy' => $format], null);
        $request = $this->browser->lastAuthnRequest();
        self::assertSame($format, $request->value('/samlp:AuthnRequest/samlp:NameIDPolicy/@Format'));
        self::assertSame('true', $request->value('/samlp:AuthnRequest/samlp:NameIDPolicy/@AllowCreate'));
    }

    public function testAcsUrlWithQueryParameters(): void
    {
        $acsUrl = 'https://ssp.dev.openconext.local/simplesaml/module.php/debugsp/acs/second-sp?SAMLRequest=request&Context=context';
        $this->loginViaSp(['idp' => self::LOCAL_IDP, 'sp' => 'second-sp', 'acsurl' => $acsUrl], null);
        self::assertSame($acsUrl, $this->browser->lastAuthnRequest()->value('/samlp:AuthnRequest/@AssertionConsumerServiceURL'));
    }

    /** With an AssertionConsumerServiceURL from the SP metadata, the IdP sends the response there */
    public function testLoginWithAcsUrl(): void
    {
        $acsUrl = 'https://ssp.dev.openconext.local/simplesaml/module.php/debugsp/acs/third-sp';
        $page = $this->loginViaSp(['idp' => self::LOCAL_IDP, 'sp' => 'third-sp', 'acsurl' => $acsUrl], 'jane-a1');

        $this->assertLoggedIn($page, 'third-sp', self::LOCAL_IDP);
        self::assertSame($acsUrl, $this->browser->lastAuthnRequest()->value('/samlp:AuthnRequest/@AssertionConsumerServiceURL'));
        self::assertSame($acsUrl, $this->browser->samlMessages('Response')[0]->destination);
    }

    /** The ADFS emulation only sets its own ACS URL when no ACS URL was given */
    public function testEmulateAdfsKeepsAcsUrl(): void
    {
        $acsUrl = 'https://ssp.dev.openconext.local/simplesaml/module.php/debugsp/acs/default-sp?SAMLRequest=mine&Context=mine';
        $this->loginViaSp(['idp' => self::GATEWAY_SFO_IDP, 'emulateadfs' => true, 'acsurl' => $acsUrl], null);

        $request = $this->browser->lastAuthnRequest();
        self::assertSame(SamlMessage::BINDING_POST, $request->binding);
        self::assertSame('ADFS.SCSA', $request->parameters['AuthMethod'] ?? null);
        self::assertSame($acsUrl, $request->value('/samlp:AuthnRequest/@AssertionConsumerServiceURL'));
    }

    /** Unknown values in a (direct login) link fall back to the defaults */
    public function testInvalidSigningAndBindingFallBackToDefaults(): void
    {
        $this->browser->get(self::SP_PATH, [
            'action' => 'login',
            'idp' => self::GATEWAY_IDP,
            'signing' => 'rsa-md5',
            'ssobinding' => 'urn:example:binding',
        ]);
        $this->browser->settle();

        $request = $this->browser->lastAuthnRequest();
        self::assertSame(SamlMessage::BINDING_REDIRECT, $request->binding);
        self::assertSame('http://www.w3.org/2001/04/xmldsig-more#rsa-sha256', $request->parameters['SigAlg'] ?? null);
        self::assertSame(self::spEntityId('default-sp'), $request->value('/samlp:AuthnRequest/saml:Issuer'));
    }

    /** All options at once, and the IdP still accepts the AuthnRequest and logs the user in */
    public function testLoginWithAllOptions(): void
    {
        $page = $this->loginViaSp([
            'idp' => self::LOCAL_IDP,
            'sp' => 'second-sp',
            'loa' => '2',
            'nameidpolicy' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified',
            'ssobinding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
            'requesterid' => 'https://sp1.example.org',
            'scopingIDP' => self::LOCAL_IDP,
            'subject' => 'urn:collab:person:institution-a.example.com:jane-a1',
            'forceauthn' => true,
            'email_extension' => 'jane@example.org',
            'sho_extension' => 'institution-a.example.com',
            'mdui_displayname' => 'My Service',
        ], 'jane-a1');

        $this->assertLoggedIn($page, 'second-sp', self::LOCAL_IDP);
        $request = $this->browser->lastAuthnRequest();
        self::assertSame(SamlMessage::BINDING_POST, $request->binding);
        self::assertNotSame('', $request->value('/samlp:AuthnRequest/ds:Signature/ds:SignatureValue'), 'AuthnRequest is signed');
    }

    /** An SFO request to the Stepup gateway, emulating the SURF MFA extension for ADFS */
    public function testSfoAdfsRequestToGateway(): void
    {
        $this->loginViaSp([
            'idp' => self::GATEWAY_SFO_IDP,
            'loa' => '2',
            'subject' => 'urn:collab:person:institution-a.example.com:jane-a1',
            'emulateadfs' => true,
        ], null);

        self::assertSame('https://gateway.dev.openconext.local/second-factor-only/single-sign-on', $this->browser->stoppedAt(), $this->browser->trail());
        $request = $this->browser->lastAuthnRequest();
        self::assertSame(SamlMessage::BINDING_POST, $request->binding);
        self::assertSame('ADFS.SCSA', $request->parameters['AuthMethod'] ?? null);
        self::assertArrayHasKey('Context', $request->parameters);
        self::assertSame(['http://dev.openconext.local/assurance/sfo-level2'], $request->values('/samlp:AuthnRequest/samlp:RequestedAuthnContext/saml:AuthnContextClassRef'));
        self::assertSame(['urn:collab:person:institution-a.example.com:jane-a1'], $request->values('/samlp:AuthnRequest/saml:Subject/saml:NameID'));
    }
}
