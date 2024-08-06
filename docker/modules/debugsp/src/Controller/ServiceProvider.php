<?php

#declare(strict_types=1);

namespace SimpleSAML\Module\debugsp\Controller;

use SimpleSAML\Logger;
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

        // Let parent handle the response
        return parent::assertionConsumerService($sourceId);
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
