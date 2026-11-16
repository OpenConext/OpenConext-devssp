<?php

declare(strict_types=1);

namespace OpenConext\DevSsp\Tests\Functional;

use OpenConext\DevSsp\Tests\FunctionalTestCase;
use OpenConext\DevSsp\Tests\SamlMessage;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Signing of the AuthnRequest by the Test SP (HTTP-Redirect binding).
 *
 * Note: the 'signature.algorithm' in the remote IdP metadata (saml20-idp-remote.php) takes precedence
 * over the algorithm selected in the Test SP. The local IdPs set rsa-sha256 there, the Stepup gateway
 * IdPs do not. So the gateway is used here; the flow stops at the redirect to the gateway.
 */
final class SigningTest extends FunctionalTestCase
{
    public static function algorithms(): iterable
    {
        yield 'rsa-sha1' => ['rsa-sha1', 'http://www.w3.org/2000/09/xmldsig#rsa-sha1', OPENSSL_ALGO_SHA1];
        yield 'rsa-sha256' => ['rsa-sha256', 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256', OPENSSL_ALGO_SHA256];
        yield 'rsa-sha384' => ['rsa-sha384', 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha384', OPENSSL_ALGO_SHA384];
        yield 'rsa-sha512' => ['rsa-sha512', 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha512', OPENSSL_ALGO_SHA512];
    }

    #[DataProvider('algorithms')]
    public function testSignedWithSelectedAlgorithm(string $signing, string $sigAlg, int $opensslAlgorithm): void
    {
        $request = $this->authnRequestToGateway(['signing' => $signing]);

        self::assertSame($sigAlg, $request->parameters['SigAlg'] ?? null);
        self::assertSame(1, $this->verifyRedirectSignature($request, $this->spCertificate(), $opensslAlgorithm), 'Signature is valid for the SP certificate');
    }

    /** With the HTTP-POST binding the AuthnRequest XML itself is signed, with the selected algorithm */
    #[DataProvider('algorithms')]
    public function testSignedWithSelectedAlgorithmUsingPostBinding(string $signing, string $sigAlg, int $opensslAlgorithm): void
    {
        $this->loginViaSp([
            'idp' => self::GATEWAY_IDP,
            'signing' => $signing,
            'ssobinding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
        ], null);

        self::assertSame('https://gateway.dev.openconext.local/authentication/single-sign-on', $this->browser->stoppedAt(), $this->browser->trail());
        $request = $this->browser->lastAuthnRequest();
        self::assertSame(SamlMessage::BINDING_POST, $request->binding);
        self::assertSame($sigAlg, $request->value('/samlp:AuthnRequest/ds:Signature/ds:SignedInfo/ds:SignatureMethod/@Algorithm'));
        // The signature is made with the key of the SP
        self::assertSame(
            preg_replace('/\s+/', '', $this->spCertificateBase64()),
            preg_replace('/\s+/', '', $request->value('/samlp:AuthnRequest/ds:Signature/ds:KeyInfo/ds:X509Data/ds:X509Certificate'))
        );
    }

    public function testUnsignedUsingPostBinding(): void
    {
        $this->loginViaSp([
            'idp' => self::GATEWAY_IDP,
            'signing' => 'none',
            'ssobinding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
        ], null);

        $request = $this->browser->lastAuthnRequest();
        self::assertSame(SamlMessage::BINDING_POST, $request->binding);
        self::assertSame(0, $request->xpath->query('/samlp:AuthnRequest/ds:Signature')->length);
    }

    public function testUnsigned(): void
    {
        $request = $this->authnRequestToGateway(['signing' => 'none']);

        self::assertArrayNotHasKey('SigAlg', $request->parameters);
        self::assertArrayNotHasKey('Signature', $request->parameters);
    }

    public function testSignedWithSecondaryKey(): void
    {
        $request = $this->authnRequestToGateway(['key' => 'secondary']);

        self::assertSame('http://www.w3.org/2001/04/xmldsig-more#rsa-sha256', $request->parameters['SigAlg'] ?? null);
        self::assertNotEmpty($request->parameters['Signature'] ?? null);
        // Signed with another key than the one in the SP metadata
        self::assertSame(0, $this->verifyRedirectSignature($request, $this->spCertificate(), OPENSSL_ALGO_SHA256));
    }

    /** The SAML tools page validates the signature of a HTTP-Redirect binding AuthnRequest */
    public function testToolsValidatesSignature(): void
    {
        $request = $this->authnRequestToGateway(['signing' => 'rsa-sha256']);

        $page = $this->browser->get('/simplesaml/tools.php');
        $page = $this->browser->submit($page->selectButton('check')->form([
            'samlrequest' => $request->url,
            'signingcert' => $this->spCertificate(),
        ]));
        $text = $page->filter('body')->text();
        self::assertStringContainsString('The signature is valid', $text);
        self::assertStringNotContainsString('Error:', $text);
    }

    public function testToolsRejectsWrongCertificate(): void
    {
        $request = $this->authnRequestToGateway(['key' => 'secondary']);

        $page = $this->browser->get('/simplesaml/tools.php');
        $page = $this->browser->submit($page->selectButton('check')->form([
            'samlrequest' => $request->url,
            'signingcert' => $this->spCertificate(),
        ]));
        $text = $page->filter('body')->text();
        self::assertStringNotContainsString('The signature is valid', $text);
        self::assertStringContainsString('Error:', $text);
    }

    /** @param array<string, string> $fields */
    private function authnRequestToGateway(array $fields): SamlMessage
    {
        $this->loginViaSp($fields + ['idp' => self::GATEWAY_IDP], null);
        self::assertSame('https://gateway.dev.openconext.local/authentication/single-sign-on', strtok((string) $this->browser->stoppedAt(), '?'), $this->browser->trail());
        $request = $this->browser->lastAuthnRequest();
        self::assertSame(SamlMessage::BINDING_REDIRECT, $request->binding);
        return $request;
    }

    /** The certificate (PEM) of the SP, from its metadata */
    private function spCertificate(): string
    {
        return "-----BEGIN CERTIFICATE-----\n" . chunk_split($this->spCertificateBase64(), 64, "\n") . "-----END CERTIFICATE-----\n";
    }

    /** The certificate of the SP (base64 DER, as in metadata), from its metadata */
    private function spCertificateBase64(): string
    {
        $browser = $this->newBrowser();
        $browser->get('/simplesaml/module.php/saml/sp/metadata.php/default-sp');
        self::assertSame(1, preg_match('#<ds:X509Certificate>([^<]+)</ds:X509Certificate>#', $browser->content(), $m));
        return (string) preg_replace('/\s+/', '', $m[1]);
    }

    /**
     * Verify the signature of a HTTP-Redirect binding message, see the SAML 2.0 bindings spec, section 3.4.4.1
     *
     * @return int 1 if valid, 0 if invalid
     */
    private function verifyRedirectSignature(SamlMessage $message, string $certificate, int $algorithm): int
    {
        // The signature is over the URL encoded values as they appear in the URL
        $raw = [];
        foreach (explode('&', (string) parse_url($message->url, PHP_URL_QUERY)) as $pair) {
            [$name, $value] = explode('=', $pair, 2) + [1 => ''];
            $raw[$name] = $value;
        }
        $signed = 'SAMLRequest=' . $raw['SAMLRequest'];
        if (isset($raw['RelayState'])) {
            $signed .= '&RelayState=' . $raw['RelayState'];
        }
        $signed .= '&SigAlg=' . $raw['SigAlg'];

        $result = openssl_verify($signed, base64_decode(urldecode($raw['Signature'])), $certificate, $algorithm);
        self::assertNotSame(-1, $result, 'openssl_verify error: ' . openssl_error_string());
        return $result;
    }
}
