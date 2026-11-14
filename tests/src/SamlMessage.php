<?php

declare(strict_types=1);

namespace OpenConext\DevSsp\Tests;

use DOMDocument;
use DOMXPath;

/**
 * A SAML message (SAMLRequest / SAMLResponse) that the browser sent, decoded from the HTTP request.
 */
final class SamlMessage
{
    public const BINDING_REDIRECT = 'HTTP-Redirect';
    public const BINDING_POST = 'HTTP-POST';

    public readonly DOMDocument $document;
    public readonly DOMXPath $xpath;

    /**
     * @param string $binding One of the BINDING_* constants
     * @param string $destination The URL the message was sent to, without query string
     * @param string $url The complete URL the message was sent to
     * @param array<string, string> $parameters All GET (Redirect binding) or POST (POST binding) parameters
     */
    public function __construct(
        public readonly string $type,
        public readonly string $binding,
        public readonly string $destination,
        public readonly string $url,
        public readonly array $parameters,
        public readonly string $xml,
    ) {
        $this->document = new DOMDocument();
        if (!$this->document->loadXML($xml)) {
            throw new \RuntimeException("Could not parse the $type as XML: $xml");
        }
        $this->xpath = new DOMXPath($this->document);
        foreach ([
            'samlp' => 'urn:oasis:names:tc:SAML:2.0:protocol',
            'saml' => 'urn:oasis:names:tc:SAML:2.0:assertion',
            'ds' => 'http://www.w3.org/2000/09/xmldsig#',
            'mdui' => 'urn:oasis:names:tc:SAML:metadata:ui',
            'gssp' => 'urn:mace:surf.nl:stepup:gssp-extensions',
        ] as $prefix => $ns) {
            $this->xpath->registerNamespace($prefix, $ns);
        }
    }

    /**
     * Decode the SAML message in a request. Returns null if the request does not carry one.
     *
     * @param array<string, mixed> $parameters POST parameters
     */
    public static function fromRequest(string $method, string $url, array $parameters): ?self
    {
        if ($method === 'GET') {
            $query = [];
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            foreach (['SAMLRequest', 'SAMLResponse'] as $type) {
                if (isset($query[$type]) && is_string($query[$type])) {
                    $xml = gzinflate((string) base64_decode($query[$type], true));
                    if ($xml === false) {
                        throw new \RuntimeException("Could not inflate the $type in $url");
                    }
                    return new self($type, self::BINDING_REDIRECT, strtok($url, '?'), $url, $query, $xml);
                }
            }
            return null;
        }
        foreach (['SAMLRequest', 'SAMLResponse'] as $type) {
            if (isset($parameters[$type]) && is_string($parameters[$type])) {
                $xml = base64_decode($parameters[$type], true);
                if ($xml === false) {
                    throw new \RuntimeException("Could not base64 decode the $type posted to $url");
                }
                return new self($type, self::BINDING_POST, strtok($url, '?'), $url, $parameters, $xml);
            }
        }
        return null;
    }

    /** Name of the root element, e.g. AuthnRequest, Response, LogoutRequest */
    public function name(): string
    {
        return $this->document->documentElement->localName;
    }

    /** String value of an XPath expression, '' when it does not match */
    public function value(string $expression): string
    {
        return (string) $this->xpath->evaluate("string($expression)");
    }

    /**
     * Text values of all nodes matching an XPath expression
     *
     * @return list<string>
     */
    public function values(string $expression): array
    {
        $values = [];
        foreach ($this->xpath->query($expression) ?: [] as $node) {
            $values[] = $node->nodeValue;
        }
        return $values;
    }
}
