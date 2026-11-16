<?php

declare(strict_types=1);

namespace OpenConext\DevSsp\Tests\Functional;

use DOMDocument;
use DOMXPath;
use OpenConext\DevSsp\Tests\FunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The pages and metadata of the devssp load and have the expected content
 */
final class PagesTest extends FunctionalTestCase
{
    public static function pages(): iterable
    {
        yield 'landing page' => ['/', 'OpenConext SimpleSAMLphp test container'];
        yield 'Test SP' => ['/simplesaml/sp.php', 'simpleSAMLphp Test SP'];
        yield 'Attribute Authority' => ['/simplesaml/aa.php', 'Attribute Authority'];
        yield 'SAML tools' => ['/simplesaml/tools.php', 'SAML Tools'];
        yield 'SimpleSAMLphp welcome' => ['/simplesaml/module.php/core/welcome', 'Welcome'];
        yield 'OpenConext theme CSS' => ['/simplesaml/module.php/openconext/assets/css/openconext.css', '#header'];
    }

    #[DataProvider('pages')]
    public function testPageLoads(string $path, string $expectedContent): void
    {
        $this->browser->get($path);
        self::assertSame(200, $this->browser->status(), $path);
        self::assertStringContainsString($expectedContent, $this->browser->content(), $path);
    }

    public function testSimpleSamlPhpAdminRequiresLogin(): void
    {
        $this->browser->get('/simplesaml/admin/');
        $this->browser->settle();
        self::assertSame(200, $this->browser->status());
        self::assertStringContainsString('/simplesaml/module.php/core/login', $this->browser->currentUrl());
    }

    public static function metadata(): iterable
    {
        yield 'local IdP' => ['/simplesaml/saml2/idp/metadata.php', self::LOCAL_IDP, 'IDPSSODescriptor'];
        yield 'EntraID mock IdP' => ['/entra-mock/saml2/idp/metadata.php', self::ENTRA_IDP, 'IDPSSODescriptor'];
        foreach (['default-sp', 'second-sp', 'third-sp', 'fourth-sp'] as $sp) {
            yield "SP $sp" => ["/simplesaml/module.php/saml/sp/metadata.php/$sp", self::spEntityId($sp), 'SPSSODescriptor'];
            yield "SP $sp (debugsp route)" => ["/simplesaml/module.php/debugsp/metadata/$sp", self::spEntityId($sp), 'SPSSODescriptor'];
        }
    }

    #[DataProvider('metadata')]
    public function testMetadata(string $path, string $entityId, string $role): void
    {
        $this->browser->get($path);
        self::assertSame(200, $this->browser->status(), $path);

        $xpath = self::metadataXPath($this->browser->content());
        self::assertSame($entityId, $xpath->evaluate('string(/md:EntityDescriptor/@entityID)'));
        self::assertSame(1, $xpath->query("/md:EntityDescriptor/md:$role")->length, "Has a $role");
        self::assertNotSame('', trim($xpath->evaluate("string(/md:EntityDescriptor/md:$role/md:KeyDescriptor//ds:X509Certificate)")), 'Has a certificate');
    }

    public function testIdpMetadataSingleSignOnService(): void
    {
        $this->browser->get('/entra-mock/saml2/idp/metadata.php');
        $xpath = self::metadataXPath($this->browser->content());
        self::assertSame(
            'https://ssp.dev.openconext.local/entra-mock/module.php/saml/idp/singleSignOnService',
            $xpath->evaluate('string(//md:SingleSignOnService[@Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect"]/@Location)')
        );
    }

    /** The debugsp module points the ACS in the SP metadata to its own ACS, so it can catch the response */
    public function testSpMetadataUsesDebugSpAssertionConsumerService(): void
    {
        $this->browser->get('/simplesaml/module.php/saml/sp/metadata.php/second-sp');
        $xpath = self::metadataXPath($this->browser->content());
        $locations = [];
        foreach ($xpath->query('//md:SPSSODescriptor/md:AssertionConsumerService[@Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST"]/@Location') as $attr) {
            $locations[] = $attr->nodeValue;
        }
        self::assertContains('https://ssp.dev.openconext.local/simplesaml/module.php/debugsp/acs/second-sp', $locations);
    }

    private static function metadataXPath(string $xml): DOMXPath
    {
        $doc = new DOMDocument();
        self::assertTrue(@$doc->loadXML($xml), "Metadata is valid XML:\n" . substr($xml, 0, 500));
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('md', 'urn:oasis:names:tc:SAML:2.0:metadata');
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
        return $xpath;
    }
}
