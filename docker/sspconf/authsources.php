<?php

// THIS FILE IS MANAGED BY ANSIBLE

# accountgen is used to add exampleauth:UserPass accounts in bulk.
# See definitions at the end of this file

include_once('accountgen.inc');
use SimpleSAML\Module\DebugSP\Auth\Source\SP;
$config = array(

    // This is a authentication source which handles admin authentication.
    'admin' => array(
        // The default is to use core:AdminPassword, but it can be replaced with
        // any authentication source.

        'core:AdminPassword',
    ),

    'example-userpass' => array(
        'exampleauth:UserPass',

        'admin:admin' => array(
            'NameID' => 'urn:collab:person:dev.openconext.local:admin',
            'uid' => array('admin'),
            'mail' => 'admin@dev.openconext.local',
            'eduPersonPrincipalName' => 'admin@dev.openconext.local',
            'givenName' => 'Admin',
            'sn' => 'Admin',
            'cn' => 'Admin',
            'displayName' => 'Admin',
            'eduPersonAffiliation' => array('employee'),
            'schacHomeOrganization' => 'dev.openconext.local',
            'schacHomeOrganizationType' => 'urn:mace:terena.org:schac:homeOrganizationType:int:university',
        ),

        // Test accounts are added using account_gen below

    ),

    // An authentication source which can authenticate against both SAML 2.0
    // and Shibboleth 1.3 IdPs.
    'default-sp' => array(
        'debugsp:SP',

        // The entity ID of this SP.
        // Can be NULL/unset, in which case an entity ID is generated based on the metadata URL.
        'entityID' => 'https://ssp.dev.openconext.local/simplesaml/module.php/saml/sp/metadata.php/default-sp',

        // The entity ID of the IdP this should SP should contact.
        // Can be NULL/unset, in which case the user will be shown a list of available IdPs.
        'idp' => NULL,

        // The URL to the discovery service.
        // Can be NULL/unset, in which case a builtin discovery service will be used.
        'discoURL' => NULL,

        'certificate' => 'sp.crt',
        'privatekey' => 'sp.key',

        // See end of file for request.sign and signature alg config!
    ),
    'second-sp' => array(
        'debugsp:SP',

        // The entity ID of this SP.
        // Can be NULL/unset, in which case an entity ID is generated based on the metadata URL.
        'entityID' => 'https://ssp.dev.openconext.local/simplesaml/module.php/saml/sp/metadata.php/second-sp',

        // The entity ID of the IdP this should SP should contact.
        // Can be NULL/unset, in which case the user will be shown a list of available IdPs.
        'idp' => NULL,

        // The URL to the discovery service.
        // Can be NULL/unset, in which case a builtin discovery service will be used.
        'discoURL' => NULL,

        'certificate' => 'sp.crt',
        'privatekey' => 'sp.key',

        // See end of file for request.sign and signature.algorithm config!
        //'redirect.sign' => TRUE,
        //'signature.algorithm' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
    ),
    'third-sp' => array(
        'debugsp:SP',

        // The entity ID of this SP.
        // Can be NULL/unset, in which case an entity ID is generated based on the metadata URL.
        'entityID' => 'https://ssp.dev.openconext.local/simplesaml/module.php/saml/sp/metadata.php/third-sp',

        // The entity ID of the IdP this should SP should contact.
        // Can be NULL/unset, in which case the user will be shown a list of available IdPs.
        'idp' => NULL,

        // The URL to the discovery service.
        // Can be NULL/unset, in which case a builtin discovery service will be used.
        'discoURL' => NULL,

        'certificate' => 'sp.crt',
        'privatekey' => 'sp.key',

        // See end of file for request.sign and signature.algorithm config!
        //'redirect.sign' => TRUE,
        //'signature.algorithm' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
    ),
    'fourth-sp' => array(
        'debugsp:SP',

        // The entity ID of this SP.
        // Can be NULL/unset, in which case an entity ID is generated based on the metadata URL.
        'entityID' => 'https://ssp.dev.openconext.local/simplesaml/module.php/saml/sp/metadata.php/fourth-sp',

        // The entity ID of the IdP this should SP should contact.
        // Can be NULL/unset, in which case the user will be shown a list of available IdPs.
        'idp' => NULL,

        // The URL to the discovery service.
        // Can be NULL/unset, in which case a builtin discovery service will be used.
        'discoURL' => NULL,

        'certificate' => 'sp.crt',
        'privatekey' => 'sp.key',

        // See end of file for request.sign and signature.algorithm config!
        //'redirect.sign' => TRUE,
        //'signature.algorithm' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
    ),

);


// Use the the global variables that were set in sp.php to modify the hosted SP configuration on the fly
// They won't always be set (e.g. when SSP is used as IdP) so we use isset() to reduce notices

if ( isset($GLOBALS['gSP_redirect_sign']) ) {
  $config['default-sp']['redirect.sign'] = $GLOBALS['gSP_redirect_sign'] ? TRUE : FALSE;
  $config['second-sp']['redirect.sign'] = $GLOBALS['gSP_redirect_sign'] ? TRUE : FALSE;
  $config['third-sp']['redirect.sign'] = $GLOBALS['gSP_redirect_sign'] ? TRUE : FALSE;
  $config['fourth-sp']['redirect.sign'] = $GLOBALS['gSP_redirect_sign'] ? TRUE : FALSE;
}
if ( isset($GLOBALS['gSP_signature_algorithm']) && strlen($GLOBALS['gSP_signature_algorithm']) > 0 ) {
    $config['default-sp']['signature.algorithm'] = $GLOBALS['gSP_signature_algorithm'];
    $config['second-sp']['signature.algorithm'] = $GLOBALS['gSP_signature_algorithm'];
    $config['third-sp']['signature.algorithm'] = $GLOBALS['gSP_signature_algorithm'];
    $config['fourth-sp']['signature.algorithm'] = $GLOBALS['gSP_signature_algorithm'];
}
if ( isset($GLOBALS['gSP_secondary_key']) && $GLOBALS['gSP_secondary_key']) {
    $config['default-sp']['certificate']='sp2.crt';
    $config['default-sp']['privatekey']='sp2.key';
    $config['second-sp']['certificate']='sp2.crt';
    $config['second-sp']['privatekey']='sp2.key';
    $config['third-sp']['certificate']='sp2.crt';
    $config['third-sp']['privatekey']='sp2.key';
    $config['fourth-sp']['certificate']='sp2.crt';
    $config['fourth-sp']['privatekey']='sp2.key';
}

if ( isset($GLOBALS['gSP_ProtocolBinding']) ) {
    $config['default-sp']['ProtocolBinding'] = $GLOBALS['gSP_ProtocolBinding'];
    $config['second-sp']['ProtocolBinding'] = $GLOBALS['gSP_ProtocolBinding'];
    $config['third-sp']['ProtocolBinding'] = $GLOBALS['gSP_ProtocolBinding'];
    $config['fourth-sp']['ProtocolBinding'] = $GLOBALS['gSP_ProtocolBinding'];
}

// Accounts for accountgen script
// Allows read email addresses to be used for multiple accounts
// The accountsgen scrip mangles the email address to e.g. joe+slug@dev.openconext.local
$accounts=array(
    // username => email (this can be a real email address)
    'user' => 'user@dev.openconext.local',
    'joe' =>  'joe@dev.openconext.local',
    'jane' => 'jane@dev.openconext.local',
);

// List of varieties of account to generate
$slugs=array(
    "1", "2", "3", "4", "5", "sms", "-yk", "-tiqr", "-u2f", "-bio", "-ra", "-raa"
);

// for a username 'joe' this will generate joe-a1, joe-a2, ..., joe-a-yk, ... joe-a-raa
// for a username 'joe' this will generate joe-b1, joe-b2, ..., joe-b-yk, ... joe-b-raa
// ...
// For all accounts the username is equal to the password. E.g. "joe-a1" / "joe-a1"
foreach ($accounts as $user => $email) {
    //                   username   , email,  schachomeorganization
    account_gen($config, "{$user}-",  $email, 'dev.openconext.local', $slugs);
    account_gen($config, "{$user}-a", $email, 'institution-a.example.com', $slugs);
    account_gen($config, "{$user}-b", $email, 'institution-b.example.com', $slugs);
    account_gen($config, "{$user}-c", $email, 'institution-c.example.com', $slugs);
    account_gen($config, "{$user}-d", $email, 'Institution-D.EXAMPLE.COM', $slugs); // Note: uppercase "I" and "D"
    account_gen($config, "{$user}-e", $email, 'Institution-e.example.com', $slugs);
    account_gen($config, "{$user}-f", $email, 'Institution-f.example.com', $slugs);
}
