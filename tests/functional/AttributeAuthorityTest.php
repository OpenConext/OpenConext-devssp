<?php

declare(strict_types=1);

namespace OpenConext\DevSsp\Tests\Functional;

use OpenConext\DevSsp\Tests\FunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The attribute authority (aa.php): attributes added there are added by the IdPs on login.
 *
 * The attributes are shared state in the container (data/aa.json); each test removes what it added.
 */
final class AttributeAuthorityTest extends FunctionalTestCase
{
    private const AA_PATH = '/simplesaml/aa.php';
    private const ATTRIBUTE = 'urn:mace:dir:attribute-def:eduPersonEntitlement';

    private string $value;

    protected function setUp(): void
    {
        parent::setUp();
        $this->value = 'urn:example:devssp-test:' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $this->deleteAttributesWithValue($this->value);
        parent::tearDown();
    }

    public static function idps(): iterable
    {
        yield 'local IdP' => [self::LOCAL_IDP];
    }

    #[DataProvider('idps')]
    public function testAddedAttributeIsReleasedOnLogin(string $idp): void
    {
        $page = $this->addAttribute('jane-a3', self::ATTRIBUTE, $this->value);
        self::assertStringContainsString('Attribute added', $page->text());
        self::assertNotNull($this->rowIndexWithValue($page, $this->value), 'Attribute is listed');

        $page = $this->loginViaSp(['idp' => $idp], 'jane-a3');

        $this->assertLoggedIn($page, 'default-sp', $idp);
        self::assertContains($this->value, self::attributes($page)[self::ATTRIBUTE] ?? []);
    }

    /** The EntraID mock limits the released attributes (core:AttributeLimit runs last), so the AA attribute is not released there */
    public function testAddedAttributeIsNotReleasedByEntraMockIdp(): void
    {
        $page = $this->addAttribute('jane-a3', self::ATTRIBUTE, $this->value);
        self::assertStringContainsString('Attribute added', $page->text());
        self::assertNotNull($this->rowIndexWithValue($page, $this->value), 'Attribute is listed');

        $page = $this->loginViaSp(['idp' => self::ENTRA_IDP], 'jane-a3');

        $this->assertLoggedIn($page, 'default-sp', self::ENTRA_IDP);
        self::assertNotContains($this->value, self::attributes($page)[self::ATTRIBUTE] ?? []);
    }

    public function testAttributeIsOnlyReleasedForItsUser(): void
    {
        $this->addAttribute('jane-a3', self::ATTRIBUTE, $this->value);

        $page = $this->loginViaSp(['idp' => self::LOCAL_IDP], 'jane-a4');

        $this->assertLoggedIn($page, 'default-sp', self::LOCAL_IDP);
        self::assertNotContains($this->value, self::attributes($page)[self::ATTRIBUTE] ?? []);
    }

    public function testDeleteAttribute(): void
    {
        $this->addAttribute('jane-a3', self::ATTRIBUTE, $this->value);

        $page = $this->deleteAttributesWithValue($this->value);

        self::assertNull($this->rowIndexWithValue($page, $this->value), 'Attribute is no longer listed');
        $page = $this->loginViaSp(['idp' => self::LOCAL_IDP], 'jane-a3');
        self::assertNotContains($this->value, self::attributes($page)[self::ATTRIBUTE] ?? []);
    }

    public static function invalidAttributes(): iterable
    {
        yield 'no username' => ['', self::ATTRIBUTE, 'value', 'Username is required'];
        yield 'no attribute' => ['jane-a3', '', 'value', 'Attribute name is required'];
        yield 'no value' => ['jane-a3', self::ATTRIBUTE, '', 'Attribute value is required'];
    }

    #[DataProvider('invalidAttributes')]
    public function testValidation(string $username, string $attribute, string $value, string $message): void
    {
        $page = $this->addAttribute($username, $attribute, $value);
        self::assertStringContainsString($message, $page->text());
    }

    private function addAttribute(string $username, string $attribute, string $value): Crawler
    {
        $page = $this->browser->get(self::AA_PATH);
        return $this->browser->submit($page->filter('#add')->form([
            'username' => $username,
            'attribute' => $attribute,
            'value' => $value,
        ]));
    }

    private function deleteAttributesWithValue(string $value): Crawler
    {
        // A separate browser, so the cleanup does not show up in the requests of the test
        $browser = $this->newBrowser();
        $page = $browser->get(self::AA_PATH);
        $index = $this->rowIndexWithValue($page, $value);
        if ($index === null) {
            return $page;
        }
        $form = $page->filter('#delete')->form();
        foreach ($form['idx'] as $checkbox) {
            if ($checkbox->availableOptionValues() === [$index]) {
                $checkbox->tick();
            }
        }
        return $browser->submit($form);
    }

    /** Index (value of the checkbox) of the row with the attribute value, or null */
    private function rowIndexWithValue(Crawler $page, string $value): ?string
    {
        $row = $page->filterXPath("//tr[td[normalize-space(.)='$value']]//input[@type='checkbox']");
        return $row->count() > 0 ? $row->attr('value') : null;
    }
}
