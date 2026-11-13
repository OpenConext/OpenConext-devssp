<?php

// This file contains the remote SAML SPs that the IdP hosted by this SSP instance can authenticate to

///////////////////////////////////////////////////////////////////////////////////
// The SPs hosted by this instance
// Even though the default-sp, second-sp, third-sp and fourth-sp SPs are hosted by this instance, 
// they are considered remote from the SSP IdP's perspective so they need to be defined here as remote SPs
// The hosted SPs are defined in sspconf/authsources.php file and are implemented in sspwww/sp.php

foreach (array('default-sp', 'second-sp', 'third-sp', 'fourth-sp') as $sp) {
    // Alternative metadata URLs:
    // - SimpleSAMLphp new style: "https://ssp.dev.openconext.local/simplesaml/module.php/saml/sp/metadata/$sp"
    // - debugsp module: "https://ssp.dev.openconext.local/simplesaml/module.php/debugsp/metadata/$sp"

    // Configure SP entity with entityID. We keep the convention of using the same entityID as the URL of the metadata
    // and use the old style metadata URL for the entityID because that is what the old version of the devssp used.
    $entityID = "https://ssp.dev.openconext.local/simplesaml/module.php/saml/sp/metadata.php/$sp";
    $metadata[$entityID] = array(
        'name' => array(
            'en' => "ssp.dev.openconext.local - Local SP ($sp)",
            'nl' => "ssp.dev.openconext.local - Local SP ($sp)",
        ),
        'AssertionConsumerService' =>
            array (
                0 =>
                    array (
                        'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
                        // Point to debugsp's module ACS
                        'Location' => "https://ssp.dev.openconext.local/simplesaml/module.php/debugsp/acs/$sp",
                        'index' => 0,
                    ),
            ),
        'SingleLogoutService' =>
            array (
                0 =>
                    array (
                        'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                        'Location' => "https://ssp.dev.openconext.local/simplesaml/module.php/saml/sp/saml2-logout.php/$sp",
                    ),
            ),
        'certificate' => 'sp.crt'
    );
}


///////////////////////////////////////////////////////////////////////////////////
// The Stepup Gateway in it's SP role
// This allows the SSP IdP in this instance to authenticate to the Stepup Gateway

$metadata['https://gateway.dev.openconext.local/authentication/metadata'] = array(
    'AssertionConsumerService' =>
        array (
            0 =>
                array (
                    'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
                    'Location' => 'https://gateway.dev.openconext.local/authentication/consume-assertion',
                    'index' => 0,
                ),
        ),
    'certificate' => 'gateway_sp.crt'
);


///////////////////////////////////////////////////////////////////////////////////
// The Stepup-AzureMFA service in its SP role towards the EntraID mock IdP hosted by
// this instance (https://ssp.dev.openconext.local/entra-mock/..., see saml20-idp-hosted.php).
// This allows the (production image of the) AzureMFA GSSP to authenticate users at the
// mock EntraID IdP.
//
// - The AuthnRequests sent by AzureMFA are signed with its SP key; the signature is
//   required at the mock IdP (validate.authnrequest) and validated against this certificate.
//   The certificate file is not part of this repository; it is provided in the cert dir
//   (mounted from OpenConext-devconf/stepup/azuremfa in the devconf setup).
// - ForceAuthn is set so that no SSO session is ever reused for AuthnRequests from
//   AzureMFA at the mock IdP: every AuthnRequest results in the login page of the mock IdP.

$metadata['https://azuremfa.dev.openconext.local/saml/metadata'] = array(
    'entityid' => 'https://azuremfa.dev.openconext.local/saml/metadata',
    'AssertionConsumerService' =>
        array (
            0 =>
                array (
                    'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
                    'Location' => 'https://azuremfa.dev.openconext.local/saml/acs',
                    'index' => 0,
                ),
        ),
    'certificate' => 'azuremfa_idp.crt',
    'ForceAuthn' => TRUE,
);
