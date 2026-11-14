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

        // Intercept SAML error responses (e.g. the Responder / AuthnFailed response that the EntraID mock
        // IdP returns when the user presses Cancel at the login page). Instead of letting SimpleSAMLphp
        // handle the error (which results in an SSP error page), store the response in the session and
        // redirect back to the Test SP (sp.php), which shows a "SAML Error Status" section with the
        // details and the full XML of the response.
        $samlError = self::parseSamlErrorResponse();
        if ($samlError !== null) {
            Logger::info('debugsp: received SAML error response, storing it in the session and redirecting to the Test SP');
            $session = Session::getSessionFromRequest();
            $session->setData('array', 'DEBUGSP_SAML_ERROR', $samlError);

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
     * Parse the raw SAMLResponse of the current request. When it is a SAML 2.0 Response with a
     * top-level StatusCode other than Success, return an array with the error details and the
     * full XML of the response. Return null when the request carries no (error) response.
     *
     * @return array|null
     */
    private static function parseSamlErrorResponse(): ?array
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

        $statusCode = $xpath->evaluate('string(/samlp:Response/samlp:Status/samlp:StatusCode/@Value)');
        if ($statusCode === '' || $statusCode === \SAML2\Constants::STATUS_SUCCESS) {
            return null; // not an error response
        }

        $subStatusCode = $xpath->evaluate('string(/samlp:Response/samlp:Status/samlp:StatusCode/samlp:StatusCode/@Value)');
        $statusMessage = $xpath->evaluate('string(/samlp:Response/samlp:Status/samlp:StatusMessage)');

        return [
            'ID' => $xpath->evaluate('string(/samlp:Response/@ID)'),
            'InResponseTo' => $xpath->evaluate('string(/samlp:Response/@InResponseTo)'),
            'IssueInstant' => $xpath->evaluate('string(/samlp:Response/@IssueInstant)'),
            'Destination' => $xpath->evaluate('string(/samlp:Response/@Destination)'),
            'Issuer' => $xpath->evaluate('string(/samlp:Response/saml:Issuer)'),
            'StatusCode' => $statusCode,
            'SubStatusCode' => $subStatusCode,
            'StatusMessage' => $statusMessage,
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
