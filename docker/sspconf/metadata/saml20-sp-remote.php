<?php

// MANAGED BY ANSIBLE

// This file contains the remote SPs that the IdP hosted by this instance can authenticate to


///////////////////////////////////////////////////////////////////////////////////
// The "default-sp" SP hosted by this instance.

$metadata['https://ssp.dev.openconext.local/simplesaml/module.php/saml/sp/metadata.php/default-sp'] = array(
    'AssertionConsumerService' =>
        array (
            0 =>
                array (
                    'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
                    'Location' => 'https://ssp.dev.openconext.local/simplesaml/module.php/saml/sp/saml2-acs.php/default-sp',
                    'index' => 0,
                ),
        ),
    'SingleLogoutService' =>
        array (
            0 =>
                array (
                    'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                    'Location' => 'https://ssp.dev.openconext.local/simplesaml/module.php/saml/sp/saml2-logout.php/default-sp',
                ),
        ),
    'certificate' => 'sp.crt'
);


///////////////////////////////////////////////////////////////////////////////////
// Stepup Gateway in it's SP role

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
