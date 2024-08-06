<?php

namespace SimpleSAML\Module\debugsp\Auth\Source;

use SAML2\Request;
use SimpleSAML\Assert\Assert;
use SimpleSAML\Configuration;
use SimpleSAML\Logger;
use SAML2\AuthnRequest;
use SAML2\Binding;
use SAML2\HTTPPost;
use SAML2\Utils;
use SimpleSAML\Module;


/*  Usage:
   - In authsourcesphp use "debugsp:SP" where you would otherwise use "saml:SP"
   - In the call to AuthSimple::requireAuth($params), AuthSimple::login($params) set 'debugsp:AssertionConsumerServiceURL'
     and 'debugsp:extraPOSTvars' to the desired values.
     E.g.:
     $params=array(
        'debugsp:AssertionConsumerServiceURL' => 'https://...',
        'debugsp:extraPOSTvars' => array(
           'SomePOSTvariable'    => 'SomeValue',
           'AnotherPOSTvariable' => 'AnotherValue'
        ),
     );
     $as->login($params);
*/

// Extend from the SimpleSAMLphp SAML 2.0 authentication source "saml:SP"
// (see: <SimpleSAMLphp>/modules/saml/src/Auth/Source/SP.php)

class SP extends \SimpleSAML\Module\saml\Auth\Source\SP
{

    private $state;

    public function __construct($info, $config)
    {
        Logger::debug('debugsp: debugsp\Auth\Source\SP::__construct(...)');
        // Get moduleURL of this module
        $moduleURL = \SimpleSAML\Module::getModuleURL('debugsp');
        Logger::debug("debugsp: ModuleURL: $moduleURL");
        $moduleDir = \SimpleSAML\Module::getModuleDir('debugsp');
        Logger::debug("debugsp: ModuleDir: $moduleDir");

        parent::__construct($info, $config);
    }


    public function startSSO(string $idp, array $state): void
    {
        $state_json = json_encode($state);
        Logger::debug("debugsp\Auth\Source\SP::startSSO('$idp', $state_json)");
        // The sequence for an authentication is:
        // - authenticate(&state: array)
        // - startSSO(string $idp, array $state)
        // - startSSO2() (private)
        // - sendSAML2AuthnRequest()

        // We need $state in sendSAML2AuthnRequest() because it holds our options like: 'debugsp:AssertionConsumerServiceURL'
        // and 'debugsp:extraPOSTvars'
        // This solution is a bit hackish, but it should work just fine. It's the only simple way to pass the state to
        // sendSAML2AuthnRequest(). We can't override startSSO2() because it's private.
        // The other option would be to completely copy the parent's implementation of startSSO() and startSSO2()

        // Store the state
        Logger::debug('debugsp: storing state');

        $this->state = $state;

        // Call parent's implementation
        parent::startSSO($idp, $state);
    }


    // Override \SimpleSAML\Auth\Source\SP::sendSAML2AuthnRequest() to allow us to make last minute changes to the
    // AuthnRequest before it is sent to the IdP
    public function sendSAML2AuthnRequest(Binding $binding, AuthnRequest $ar): void
    {
        Logger::debug('debugsp: debugsp\Auth\Source\SP::sendSAML2AuthnRequest(...)');
        if (isset($this->state['debugsp:AssertionConsumerServiceURL'])) {
            Logger::notice('debugsp:AssertionConsumerServiceURL set to ' . $this->state['debugsp:AssertionConsumerServiceURL']);
            // Set the AssertionConsumerServiceURL in the AuthnRequest
            $acs = $this->state['debugsp:AssertionConsumerServiceURL'];
            $ar->setAssertionConsumerServiceURL($acs);
        }

        if ($binding instanceof HTTPPost) {
            Logger::debug('debugsp: binding is HTTPPost');
            // Send a SAML AuthnRequest using to HTTP-POST binding
            // replicate \SAML2\HTTPPost::send(Message $message) so we can set additional POST variables
            $destination = $ar->getDestination();
            $relayState = $ar->getRelayState();

            $post = array();

            // Set extra POST variables
            if (isset($this->state['debugsp:extraPOSTvars'])) {
                assert(is_array($this->state['debugsp:extraPOSTvars']), 'debugsp:extraPOSTvars must be array()');
                foreach ($this->state['debugsp:extraPOSTvars'] as $key => $value) {
                    $post[$key] = $value;
                    Logger::info('debugsp:extraPOSTvars: ' . $key . ' => ' . $value);
                }
            }

            // Create SAMLRequest and add it to the POST variables the standard way
            $msgStr = $ar->toSignedXML();
            Utils::getContainer()->debugMessage($msgStr, 'out');
            $msgStr = $msgStr->ownerDocument->saveXML($msgStr);

            $post['SAMLRequest'] = base64_encode($msgStr);

            if ($relayState !== null) {
                $post['RelayState'] = $relayState;
            }

            Logger::debug('debugsp: sending request using HTTP-POST binding');
            Utils::getContainer()->postRedirect($destination, $post);

            // Does not return
            Assert::true(false);
        }

        // Use parent's implementation ( which is just: $binding->send($ar); )
        Logger::debug('debugsp: sending authn request using parent implementation');
        parent::sendSAML2AuthnRequest($binding, $ar);

        // Does not return
        Assert::true(false);
    }

    /**
     * Retrieve the metadata of this SP.
     *
     * @return \SimpleSAML\Configuration  The metadata of this SP.
     */
    public function getMetadata(): Configuration
    {
        Logger::debug('debugsp: debugsp\Auth\Source\SP::getMetadata()');

        $metadata = parent::getMetadata();

        return $metadata;
    }

    public function getMetadataURL(): string
    {
        Logger::debug('debugsp: debugsp\Auth\Source\SP::getMetadataURL()');

        // Create URL that matches our route: debug-sp-metadata
        return Module::getModuleURL('debugsp/metadata/' . urlencode($this->authId));
    }

    /**
     * Retrieve the metadata array of this SP, as a remote IdP would see it.
     *
     * @return array The metadata array for its use by a remote IdP.
     */
    public function getHostedMetadata(): array
    {
        Logger::debug('debugsp: debugsp\Auth\Source\SP::getHostedMetadata()');

        $metadata = parent::getHostedMetadata();

        // Override the AssertionConsumerService URL in the metadata to our own ACL URL to we can catch it

        // Create URL that matches our route: debug-sp-assertionConsumerService
        $acsLocation = Module::getModuleURL('debugsp/acs/') . urlencode($this->authId);
        foreach ($metadata['AssertionConsumerService'] as $key => $acs) {
            switch ($metadata['AssertionConsumerService'][$key]['Binding']) {
                case 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST':
                case 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect':
                    Logger::debug("debugsp: setting AssertionConsumerService Location of binding $key to $acsLocation");
                    $metadata['AssertionConsumerService'][$key]['Location'] = $acsLocation;
            }
        }
        return $metadata;
    }
}