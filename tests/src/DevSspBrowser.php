<?php

declare(strict_types=1);

namespace OpenConext\DevSsp\Tests;

use PHPUnit\Framework\Assert;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpClient\HttpClient;

/**
 * A minimal browser for driving the devssp: it keeps cookies, follows redirects, "auto-submits" the
 * SAML HTTP-POST binding forms (normally done by JavaScript) and logs in at the IdP login page.
 *
 * Every response is checked for PHP errors in the page, because these come with a HTTP 200 status.
 */
final class DevSspBrowser
{
    private const MAX_STEPS = 30;

    private HttpBrowser $browser;

    /** @var list<string> Human-readable list of the requests that were made, for failure messages */
    private array $trail = [];

    /** @var list<array{method: string, uri: string, parameters: array<string, mixed>}> All requests that were made */
    private array $requests = [];

    /** @var int Number of times the IdP login form was submitted */
    private int $logins = 0;

    /** @var bool Do not fail on HTTP 5xx responses, for tests that expect an error page */
    private bool $allowServerErrors = false;

    public function allowServerErrors(): void
    {
        $this->allowServerErrors = true;
    }

    public function __construct(private readonly string $baseUrl, bool $verifyTls)
    {
        $this->browser = new HttpBrowser(HttpClient::create([
            'verify_peer' => $verifyTls,
            'verify_host' => $verifyTls,
            'timeout' => 30,
        ]));
        // We follow redirects ourselves, so we can see (and check) each step
        $this->browser->followRedirects(false);
    }

    public function url(string $path): string
    {
        return str_starts_with($path, 'http') ? $path : rtrim($this->baseUrl, '/') . $path;
    }

    /**
     * GET a page without following redirects.
     *
     * @param array<string, string> $query
     */
    public function get(string $path, array $query = []): Crawler
    {
        $url = $this->url($path);
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }
        $crawler = $this->browser->request('GET', $url);
        $this->recordStep();
        return $crawler;
    }

    /** POST to a page without following redirects. */
    public function post(string $path, array $parameters): Crawler
    {
        $crawler = $this->browser->request('POST', $this->url($path), $parameters);
        $this->recordStep();
        return $crawler;
    }

    public function submit(Form $form): Crawler
    {
        $crawler = $this->browser->submit($form);
        $this->recordStep();
        return $crawler;
    }

    /**
     * Continue like a user would: follow redirects, submit SAML HTTP-POST binding forms and, when
     * $username is given, log in at the IdP login page (once). Stops at the first page that needs
     * a decision, e.g. the Test SP page, or the IdP login page when no $username is given.
     *
     * Does not leave the devssp: a redirect or SAML POST to another host (e.g. the Stepup gateway)
     * is not followed. The SAML message that would be sent is still available from samlMessages().
     *
     * @param bool $cancel Press the Cancel button on the IdP login page instead of logging in
     */
    public function settle(?string $username = null, bool $cancel = false): Crawler
    {
        $crawler = $this->browser->getCrawler();
        for ($step = 0; $step < self::MAX_STEPS; $step++) {
            $status = $this->status();
            if ($status >= 300 && $status < 400) {
                $location = (string) $this->browser->getInternalResponse()->getHeader('Location');
                if ($this->isExternal($location)) {
                    $this->recordExternal('GET', $location, []);
                    return $crawler;
                }
                $crawler = $this->browser->followRedirect();
                $this->recordStep();
                continue;
            }
            if ($status !== 200) {
                return $crawler;
            }

            $samlForm = $crawler->filter('form')->reduce(
                static fn (Crawler $form): bool => $form->filter('input[name="SAMLRequest"], input[name="SAMLResponse"]')->count() > 0
            );
            if ($samlForm->count() > 0) {
                $form = $samlForm->first()->form();
                if ($this->isExternal($form->getUri())) {
                    $this->recordExternal($form->getMethod(), $form->getUri(), $form->getPhpValues());
                    return $crawler;
                }
                $crawler = $this->submit($form);
                continue;
            }

            if ($username !== null && $crawler->filter('form#f input[name="username"]')->count() > 0) {
                if ($cancel) {
                    // What js/login.js does when Cancel is clicked
                    $form = $crawler->filter('#cancel_button')->form(['username' => 'cancel']);
                } else {
                    $form = $crawler->filter('form#f')->form(['username' => $username]);
                }
                $username = null;   // log in only once
                $this->logins++;
                $crawler = $this->submit($form);
                continue;
            }

            return $crawler;
        }
        Assert::fail('Too many steps, stopped following the flow:' . $this->trail());
    }

    public function status(): int
    {
        return $this->browser->getInternalResponse()->getStatusCode();
    }

    public function content(): string
    {
        return $this->browser->getInternalResponse()->getContent();
    }

    public function currentUrl(): string
    {
        return $this->browser->getInternalRequest()->getUri();
    }

    /** The username field of the IdP login form on the current page (empty when not on the IdP login page) */
    public function filterLoginForm(): Crawler
    {
        return $this->browser->getCrawler()->filter('form#f input[name="username"]');
    }

    /** Number of times the IdP login form was submitted */
    public function logins(): int
    {
        return $this->logins;
    }

    /**
     * All SAML messages the browser sent, in order
     *
     * @return list<SamlMessage>
     */
    public function samlMessages(?string $name = null): array
    {
        $messages = [];
        foreach ($this->requests as $request) {
            $message = SamlMessage::fromRequest($request['method'], $request['uri'], $request['parameters']);
            if ($message !== null && ($name === null || $message->name() === $name)) {
                $messages[] = $message;
            }
        }
        return $messages;
    }

    /** The last AuthnRequest that the browser sent */
    public function lastAuthnRequest(): SamlMessage
    {
        $requests = $this->samlMessages('AuthnRequest');
        Assert::assertNotEmpty($requests, 'No AuthnRequest was sent' . $this->trail());
        return $requests[array_key_last($requests)];
    }

    /** The requests that were made, for use in failure messages */
    public function trail(): string
    {
        return "\nRequests:\n  " . implode("\n  ", $this->trail);
    }

    /** The flow stopped at the last request: it goes to another host. See stoppedAt() */
    private ?string $stoppedAt = null;

    /** The URL of the request to another host where settle() stopped, or null */
    public function stoppedAt(): ?string
    {
        return $this->stoppedAt;
    }

    private function isExternal(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        return is_string($host) && $host !== parse_url($this->baseUrl, PHP_URL_HOST);
    }

    /** Record a request to another host that we did not make, so its SAML message is available */
    private function recordExternal(string $method, string $uri, array $parameters): void
    {
        $this->requests[] = ['method' => $method, 'uri' => $uri, 'parameters' => $parameters];
        $this->trail[] = sprintf('%s %s (not followed: other host)', $method, $uri);
        $this->stoppedAt = $uri;
    }

    private function recordStep(): void
    {
        $request = $this->browser->getInternalRequest();
        $response = $this->browser->getInternalResponse();
        $this->requests[] = ['method' => $request->getMethod(), 'uri' => $request->getUri(), 'parameters' => $request->getParameters()];
        $this->trail[] = sprintf('%s %s -> %d', $request->getMethod(), $request->getUri(), $response->getStatusCode());

        // PHP errors are shown in the page (display_errors), while the HTTP status is often still 200
        if (preg_match('/(Fatal error|Parse error|Warning|Notice|Deprecated)(<\/b>)?:\s+.*\son line (<b>)?\d+/', $response->getContent(), $m)) {
            Assert::fail('PHP error in the response: ' . strip_tags($m[0]) . $this->trail());
        }
        if ($response->getStatusCode() >= 500 && !$this->allowServerErrors) {
            Assert::fail('Server error' . $this->trail() . "\n" . self::pageText($response->getContent()));
        }
    }

    /** Readable text of an HTML page, for failure messages */
    public static function pageText(string $html, int $max = 2000): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) preg_replace('#<(style|script)\b.*?</\1>#s', '', $html))));
        return mb_substr($text, 0, $max);
    }
}
