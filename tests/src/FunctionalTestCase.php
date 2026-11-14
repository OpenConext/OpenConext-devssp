<?php

declare(strict_types=1);

namespace OpenConext\DevSsp\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Base class for the devssp functional tests.
 *
 * - Each test gets a fresh browser (no cookies, so no SSO sessions from other tests)
 * - After each test, the error log of the devssp is checked: any error that the test caused
 *   fails the test, unless the test allowed it using allowLogErrors()
 */
abstract class FunctionalTestCase extends TestCase
{
    // Entity IDs, as configured in docker/sspconf/metadata and docker/sspconf/authsources.php
    protected const LOCAL_IDP = 'https://ssp.dev.openconext.local/simplesaml/saml2/idp/metadata.php';
    protected const ENTRA_IDP = 'https://ssp.dev.openconext.local/entra-mock/saml2/idp/metadata.php';
    // The Stepup gateway is not part of the devssp: flows to it are stopped at the devssp (see DevSspBrowser::settle())
    protected const GATEWAY_IDP = 'https://gateway.dev.openconext.local/authentication/metadata';
    protected const GATEWAY_SFO_IDP = 'https://gateway.dev.openconext.local/second-factor-only/metadata';
    protected const SP_ENTITY_ID_PREFIX = 'https://ssp.dev.openconext.local/simplesaml/module.php/saml/sp/metadata.php/';

    protected const SP_PATH = '/simplesaml/sp.php';

    protected DevSspBrowser $browser;

    private ?LogWatcher $log = null;

    /** @var list<string> */
    private array $allowedLogPatterns = [];

    protected function setUp(): void
    {
        $this->browser = $this->newBrowser();
        $this->log = LogWatcher::fromEnvironment();
        $this->log?->mark();
    }

    protected function assertPostConditions(): void
    {
        if ($this->log === null) {
            return;
        }
        $errors = $this->log->errorsSinceMark($this->allowedLogPatterns);
        self::assertSame(
            [],
            $errors,
            "The test caused errors in the devssp log:\n" . implode("\n", array_slice($errors, 0, 40))
        );
    }

    protected function newBrowser(): DevSspBrowser
    {
        return new DevSspBrowser(
            (string) getenv('DEVSSP_BASE_URL'),
            filter_var(getenv('DEVSSP_VERIFY_TLS'), FILTER_VALIDATE_BOOLEAN)
        );
    }

    /** Allow errors in the log that match $pattern (and the other log lines of the same SimpleSAMLphp message) */
    protected function allowLogErrors(string $pattern): void
    {
        $this->allowedLogPatterns[] = $pattern;
    }

    protected static function spEntityId(string $sp): string
    {
        return self::SP_ENTITY_ID_PREFIX . $sp;
    }

    /**
     * Open the Test SP, fill in its login form and press Login, then follow the flow
     * (see DevSspBrowser::settle()).
     *
     * @param array<string, string|bool> $fields Values for the fields of the login form of sp.php,
     *                                           e.g. ['idp' => self::LOCAL_IDP, 'forceauthn' => true]
     * @param ?string $username User to log in as at the IdP; null to stop at the IdP login page
     */
    protected function loginViaSp(array $fields, ?string $username, bool $cancel = false, ?DevSspBrowser $browser = null): Crawler
    {
        $browser ??= $this->browser;
        $crawler = $browser->get(self::SP_PATH);
        self::assertSame(200, $browser->status(), 'Test SP page' . $browser->trail());
        $form = $crawler->filter('#login_3')->form();
        foreach ($fields as $name => $value) {
            $form[$name] = $value;  // throws when the field or option does not exist in the form
        }
        $browser->submit($form);
        return $browser->settle($username, $cancel);
    }

    /** Assert that the Test SP page shows that we are logged in */
    protected function assertLoggedIn(Crawler $page, string $sp, string $idp): void
    {
        self::assertStringEndsWith(self::SP_PATH . '?sp=' . $sp, strtok($this->browser->currentUrl(), '#'), 'Ended at the Test SP' . $this->browser->trail());
        self::assertSame($sp, self::labelValue($page, 'You are logged in to SP:'), 'Logged in at the SP' . $this->browser->trail() . "\n" . DevSspBrowser::pageText($this->browser->content()));
        self::assertSame($idp, self::labelValue($page, 'IdP EnitytID:'));
    }

    /**
     * Value shown next to a label on the Test SP page: <label>$label</label><code>value</code>
     * Returns null when the label is not on the page.
     */
    protected static function labelValue(Crawler $page, string $label, string $context = '//'): ?string
    {
        $code = $page->filterXPath($context . "label[normalize-space(.)='$label']/following-sibling::code[1]");
        return $code->count() > 0 ? trim($code->first()->text()) : null;
    }

    /**
     * The NameID shown on the Test SP page
     *
     * @return array{Format: ?string, Value: ?string}
     */
    protected static function nameId(Crawler $page): array
    {
        $context = "//h3[normalize-space(.)='NameID']/following-sibling::p[1]/";
        return [
            'Format' => self::labelValue($page, 'Format:', $context),
            'Value' => self::labelValue($page, 'Value:', $context),
        ];
    }

    /**
     * The attributes shown on the Test SP page, by attribute name
     *
     * @return array<string, list<string>>
     */
    protected static function attributes(Crawler $page): array
    {
        $attributes = [];
        $page->filterXPath("//h3[normalize-space(.)='Attributes']/following-sibling::div[1]//tr[td]")->each(
            static function (Crawler $row) use (&$attributes): void {
                $cells = $row->filter('td');
                // The first cell contains the attribute name, optionally followed by a friendly name
                $name = preg_split('/\s+/', trim($cells->eq(0)->text()))[0];
                $attributes[$name] = $cells->eq(1)->filter('code')->each(static fn (Crawler $c): string => trim($c->text()));
            }
        );
        return $attributes;
    }
}
