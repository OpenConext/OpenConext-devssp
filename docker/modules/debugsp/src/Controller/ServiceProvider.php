<?php

#declare(strict_types=1);

namespace SimpleSAML\Module\debugsp\Controller;

use SimpleSAML\Logger;
use SimpleSAML\Session;
use SimpleSAML\Utils\HTTP;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use SimpleSAML\HTTP\RunnableResponse;

class ServiceProvider extends \SimpleSAML\Module\saml\Controller\ServiceProvider
{
    public function __construct(
        protected \SimpleSAML\Configuration $config,
        protected \SimpleSAML\Session $session
    ) {
        Logger::info('debugsp\Controller\ServiceProvider::__construct()');

        parent::__construct($config, $session);
    }

    /**
     * Handler for the Assertion Consumer Service.
     *
     * @param string $sourceId
     * @return \SimpleSAML\HTTP\RunnableResponse
     */
    public function assertionConsumerService(string $sourceId): RunnableResponse
    {
        Logger::info("debugsp\Controller\ServiceProvider::assertionConsumerService($sourceId)");

        // Rename the "_SAMLResponse" variable that used by the ADFS SFO extension back to the SAML HTTP-POST standard
        // "SAMLResponse" and then hand over processing to the standard SSP ACS processing

        if (isset($_POST['_SAMLResponse'])) {
            // Make ADFS response look like a normal SAML response
            Logger::info("Renaming '_SAMLResponse' to 'SAMLResponse'");
            $_POST['SAMLResponse'] = $_POST['_SAMLResponse'];
        }

        // Store the SAML Response as received (verbatim) in the session, so the Test SP (sp.php) can show it.
        // This saves a trip to the SAML tracer browser plugin.
        $samlResponse = self::parseSamlResponse();
        if ($samlResponse === null) {
            // Let parent handle the request (and the error)
            return parent::assertionConsumerService($sourceId);
        }
        $session = Session::getSessionFromRequest();
        $session->setData('array', 'DEBUGSP_SAML_RESPONSE', $samlResponse);

        // Intercept SAML error responses (e.g. the Responder / AuthnFailed response that the EntraID mock
        // IdP returns when the user presses Cancel at the login page). Instead of letting SimpleSAMLphp
        // handle the error (which results in an SSP error page), redirect back to the Test SP (sp.php),
        // which shows a "SAML Error Status" section with the details and the XML of the response.
        if ($samlResponse['StatusCode'] !== '' && $samlResponse['StatusCode'] !== \SAML2\Constants::STATUS_SUCCESS) {
            Logger::info('debugsp: received SAML error response, redirecting to the Test SP');

            $httpUtils = new HTTP();
            $relayState = $_REQUEST['RelayState'] ?? null;
            if (is_string($relayState) && strlen($relayState) > 0) {
                return new RunnableResponse([$httpUtils, 'redirectTrustedURL'], [$httpUtils->checkURLAllowed($relayState)]);
            }

            // No (valid) RelayState: redirect to the Test SP for this authsource
            $returnTo = $httpUtils->getBaseURL() . 'sp.php?sp=' . urlencode($sourceId);
            return new RunnableResponse([$httpUtils, 'redirectTrustedURL'], [$returnTo]);
        }

        // Let parent handle the response
        return parent::assertionConsumerService($sourceId);
    }

    /**
     * Parse the raw SAMLResponse of the current request. Returns an array with the main fields of the
     * response and its assertion, and the XML of the response as received ('xml').
     * Returns null when the request carries no SAML 2.0 Response.
     *
     * @return array|null
     */
    private static function parseSamlResponse(): ?array
    {
        $encoded = $_POST['SAMLResponse'] ?? $_GET['SAMLResponse'] ?? null;
        if (!is_string($encoded) || $encoded === '') {
            return null;
        }

        $xml = base64_decode($encoded, true);
        if ($xml === false || $xml === '') {
            return null;
        }

        $doc = new \DOMDocument();
        if (!@$doc->loadXML($xml)) {
            // HTTP-Redirect binding responses are deflated after base64 encoding
            $inflated = @gzinflate($xml);
            if ($inflated === false || !@$doc->loadXML($inflated)) {
                return null;
            }
            $xml = $inflated;
        }

        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('samlp', 'urn:oasis:names:tc:SAML:2.0:protocol');
        $xpath->registerNamespace('saml', 'urn:oasis:names:tc:SAML:2.0:assertion');

        if ($xpath->query('/samlp:Response')->length !== 1) {
            return null; // not a SAML 2.0 Response
        }

        $value = static fn (string $expression): string => (string) $xpath->evaluate("string($expression)");
        $assertion = '/samlp:Response/saml:Assertion';

        return [
            'ID' => $value('/samlp:Response/@ID'),
            'InResponseTo' => $value('/samlp:Response/@InResponseTo'),
            'IssueInstant' => $value('/samlp:Response/@IssueInstant'),
            'Destination' => $value('/samlp:Response/@Destination'),
            'Issuer' => $value('/samlp:Response/saml:Issuer'),
            'StatusCode' => $value('/samlp:Response/samlp:Status/samlp:StatusCode/@Value'),
            'SubStatusCode' => $value('/samlp:Response/samlp:Status/samlp:StatusCode/samlp:StatusCode/@Value'),
            'StatusMessage' => $value('/samlp:Response/samlp:Status/samlp:StatusMessage'),
            'EncryptedAssertion' => $xpath->query('/samlp:Response/saml:EncryptedAssertion')->length > 0 ? 'yes' : '',
            'Assertion ID' => $value("$assertion/@ID"),
            'Assertion IssueInstant' => $value("$assertion/@IssueInstant"),
            'Conditions NotBefore' => $value("$assertion/saml:Conditions/@NotBefore"),
            'Conditions NotOnOrAfter' => $value("$assertion/saml:Conditions/@NotOnOrAfter"),
            'Audience' => $value("$assertion/saml:Conditions/saml:AudienceRestriction/saml:Audience"),
            'AuthnInstant' => $value("$assertion/saml:AuthnStatement/@AuthnInstant"),
            'SessionNotOnOrAfter' => $value("$assertion/saml:AuthnStatement/@SessionNotOnOrAfter"),
            'SessionIndex' => $value("$assertion/saml:AuthnStatement/@SessionIndex"),
            'xml' => $xml,
        ];
    }


    /**
     * Metadata endpoint for SAML SP
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param string $sourceId
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function metadata(Request $request, string $sourceId): Response
    {
        Logger::info("debugsp\Controller\ServiceProvider::metadata($sourceId)");

        // Let parent handle the response
        return parent::metadata($request, $sourceId);
    }
}
