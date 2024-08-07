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
