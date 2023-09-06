<?php

$_SERVER['HTTPS'] = 'on';

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// List of the avaiable remote SAML 2.0 identity providers in the SP interface
// The SP interface allows selection of the IdP and the (Requested) AuthnContextClassRef
// The IdP must be configured in saml20-idp-remote.php

$gIDPmap = array(
    /*
    'EntityID of the IdP as it appers in saml20-idp-remote.php' => array(
        'name' => 'Displayname of the IdP in the SP interface',
        'loa' => array(
          1 => 'AuthnContextClassRef for loa 1',
          2 => 'AuthnContextClassRef for loa 2',
          3 => 'AuthnContextClassRef for loa 3',
        ),
    )
    */
    'https://gateway.dev.openconext.local/authentication/metadata' => array(
        'name' => 'OpenConext Stepup Gateway - gateway.dev.openconext.local',
        'loa' => array(
            1 => 'http://dev.openconext.local/assurance/loa1',
            2 => 'http://dev.openconext.local/assurance/loa2',
            3 => 'http://dev.openconext.local/assurance/loa3',
        ),
    ),
    'https://gateway.dev.openconext.local/second-factor-only/metadata' => array(
        'name' => 'OpenConext Stepup Gateway - gateway.dev.openconext.local - SFO',
        'loa' => array(
            1 => 'https://dev.openconext.local/assurance/sfo-level1', // Note does not exist
            2 => 'https://dev.openconext.local/assurance/sfo-level2',
            3 => 'https://dev.openconext.local/assurance/sfo-level3',
        ),
    ),
    'https://ssp.dev.openconext.local/simplesaml/saml2/idp/metadata.php' => array(
        'name' => 'Local SSP IdP - ssp.dev.openconext.local',
        'loa' => array(
            1 => '',
            2 => '',
            3 => '',
        ),
    ),
);
