<?php

// This is the IdP that is hosted by this SSP instance.
// It is used as a "remote IdP" by the stepup gateway and is configured to emulate the behaviour of 
// OpenConext engineblock when it is used as an IdP proxy for the Stepup-Gateway
// See: authsources.php for the list of accounts that are defined at this IdP

// Since Stepup-Gateway 3.4.5 there are two ways to pass the ID of the user to the Stepup-Gateway:
// 1. In the NameID in the Subject of the SAML Assertion. In this case the Subject NameID to pass the the SP
//    behind the Stepup-Gateway must be specified in eduPersonTargetedID attribute in the SAML Assertion.
// 2. In the urn:mace:surf.nl:attribute-def:internal-collabPersonId attribute in the SAML Assertion.

// The presence of the "urn:mace:surf.nl:attribute-def:internal-collabPersonId" decides which of the two methods
// is used by the Stepup-Gateway: 
// * If he attribute is not present (method 1), the Stepup-Gateway will use the value of the NameID in the Subject as the 
// ID of the user. The NameID in the Subject to the SP must be specified in the eduPersonTargetedID attribute in the SAML Assertion and
// the Stepup-Gateway will copy the NameId from the eduPersonTargetedID attribute to the Subject before passing the Assertion on to the SP.
// * If the attribute is present (method 2), the Stepup-Gateway will use that value of the attribute as the 
// ID of the user. The internal-collabPersonId will be filtered from the attributes present in the Assertions before it is
// passed to the SP. The IdP does not have to add en eduPersonTargetedID attribute to the Assertion.
// 
// See https://www.pivotaltracker.com/n/projects/1163646/stories/181115261


// Method 1 - use Subject and eduPersonTargetedID
$subject_method1_authproc = array(
    
    // Generate an unspecified NameID for use by this IdP
    // Note that this NameID won't be used until it is "selected" in the saml20-idp-hosted.php by adding:
    //     'NameIDFormat' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified',
    //
    // Use the value of the "NameID" attribute from the authsource as value for the NameID
    1 => array(
        'class' => 'saml:AttributeNameID',
        'attribute' => 'NameID',
        'identifyingAttribute' => 'NameID',
        'Format' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified',

        // Don't add NameQualifier and SPNameQualifier attributes to the generated NameID
        'NameQualifier' => FALSE,
        'SPNameQualifier' => FALSE,
    ),

    // Copy the NameID to the eduPersonTargetedID attribute
    // This generates a eduPersonTargetedID with a PERSISTENT targeted NameID
    //
    // Note: #3 below generates a eduPersonTargetedID with an UNSPECIFIED targeted NameID
    //       and will overwrite this eduPersonTargetedID
    /*
    2 => array(
        'class' => 'core:TargetedID', // Generate a eduPersonTargetedID attribute
        'attribute' => 'NameID',
        'identifyingAttribute' => 'NameID',
        'nameId' => TRUE,   // Use the "Nested" NameID format
        // Don't add NameQualifier and SPNameQualifier attributes to the generated NameID
        'NameQualifier' => FALSE,
        'SPNameQualifier' => FALSE,
    ),
    */
    
    // Create an eduPersonTargetedID attribute with an unspecified NameID with the value
    // of the "NameID" attribute from the authsource.
    3 => array(
        'class' => 'core:PHP',
        'code' =>
            '
            $nameId = new \SAML2\XML\saml\NameID();
            $nameId->setValue($attributes["NameID"][0]);  // Use value of "NameID" attribute
            $nameId->setFormat(\SAML2\Constants::NAMEID_UNSPECIFIED); // Unspecified NameID
            //$nameId->setSPProvidedID = "...";
            $doc = \SAML2\DOMDocumentFactory::create();
            $root = $doc->createElement("root");
            $doc->appendChild($root);
            $nameId->toXML($root);
            $eduPersonTargetedID = $doc->saveXML($root->firstChild);
            $attributes["eduPersonTargetedID"] = array($eduPersonTargetedID);
            ',
    ),

    // Remove the NameID attribute from the attributes
    // If you do not remove the nameID attribute here, it will be renamed to collabPersonId by the AttributeMap below
    5 => array(
        'class' => 'core:AttributeAlter',
        'subject' => 'NameID',
        'pattern' => '/.*/',
        '%remove',
    ),

    // Convert "short" atribute names (uid, mail, eduPersonTargetedID, ...) to their long urn:mace...
    // equivalent
    // The map is defined in sspattributemap/Openconext_short_to_urn.php
    // This will also convert the NameID attribute to urn:mace:surf.nl:attribute-def:internal-collabPersonId (method 2)
    // when the NameId is present in the attributes
    10 => array(
        'class' => 'core:AttributeMap',
        'Openconext_short_to_urn'
    ),
);

$subject_method2_authproc = array( // internal-collabPersonId

    // Generate an unspecified NameID for use by this IdP
    // Note that this NameID won't be used until it is "selected" in the saml20-idp-hosted.php by adding:
    //     'NameIDFormat' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified',
    //
    // Use the value of the "NameID" attribute from the authsource as value for the NameID
    1 => array(
        'class' => 'saml:AttributeNameID',
        'attribute' => 'NameID',
        'identifyingAttribute' => 'NameID',
        'Format' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified',

        // Don't add NameQualifier and SPNameQualifier attributes to the generated NameID
        'NameQualifier' => FALSE,
        'SPNameQualifier' => FALSE,
    ),

    // Convert "short" atribute names (uid, mail, eduPersonTargetedID, ...) to their long urn:mace...
    // equivalent
    // The map is defined in sspattributemap/Openconext_short_to_urn.php
    // This will also convert the NameID attribute to urn:mace:surf.nl:attribute-def:internal-collabPersonId (method 2)
    // when the NameId is present in the attributes
    10 => array(
        'class' => 'core:AttributeMap',
        'Openconext_short_to_urn'
    ),
);

$attribute_authority_authproc=array(
    // Apply attributes from the /var/www/simplesaml/data/aa.json file managed by the aa.php script, our "Attribute Authority"
    // If a user is found in the aa.json file, the attributes set for that user will override the attributes from the authsource
    // aa.json is a JSON file with an array of objects with the following structure:
    // [ { "u": "user1", "a": "attr1", "v": "value1" }, ... ]
    100 => array(   // We want to run this on the final attributes of the IdP
        'class' => 'core:PHP',
        // The current attributes are in $attributes
        'code' => '
            $aa_file = "/var/www/simplesaml/data/aa.json";
            
            if (isset($attributes["urn:mace:dir:attribute-def:uid"])) {
                $uid = $attributes["urn:mace:dir:attribute-def:uid"][0]; // The uid to match
                
                // Load the attributes from the aa.json file                
                $aa_attributes = [];
                if (file_exists($aa_file)) {
                    $aa_attributes = json_decode(file_get_contents($aa_file), true);
                }
                if (false === $aa_attributes) {
                    // Ignore read and parse errors
                    $aa_attributes = []; 
                }
                
                //throw new Exception("In AA for $uid: " . print_r($aa_attributes, true));
                
                // Look for attributes for the user $uid in the aa.json file
                $user_attributes = [];
                foreach ($aa_attributes as $aa_attribute) {
                    if ($aa_attribute["u"] == $uid) {
                        // Found the user, add the attribute to the user_attributes
                        // A user can have multiple attributes, and can have multiple values for an attribute
                        if (!isset($user_attributes[$aa_attribute["a"]])) {
                            $user_attributes[$aa_attribute["a"]] = [];
                        }
                        $user_attributes[$aa_attribute["a"]][] = $aa_attribute["v"];  
                    }
                }
                
                // Merge the user_attributes with the current attributes, attributes in user_attributes will override 
                // the current attributes
                $attributes = array_merge($attributes, $user_attributes);    
            }          
        ',
    ),
);


// Shared authproc filter for both IdPs below: simulate a user cancelling the authentication
// (the "cancel" account, submitted by the Cancel button on the login page): send a SAML
// error response (Responder / AuthnFailed) to the SP, using the same responder
// SimpleSAMLphp uses for authentication errors. (A plain "throw" would not work here:
// with ForceAuthn the authproc runs after the login completed, where an exception would
// only result in an error page, not a SAML response.)
//
// The SSO session of the authsource is cleared first: the "cancel" login must not leave
// an authenticated session behind, otherwise following AuthnRequests without ForceAuthn
// would be SSO-ed as the "cancel" user (and fail with the same SAML error).
//
// Note: this filter must run before the AttributeMap filter (key 10), which renames "uid"
// to its urn:mace:... equivalent.
$cancel_authproc = array(
    2 => array(
        'class' => 'core:PHP',
        'code' => '
            if (isset($attributes["uid"][0]) && $attributes["uid"][0] === "cancel") {
                if (isset($state["\SimpleSAML\Auth\Source.id"])) {
                    \SimpleSAML\Session::getSessionFromRequest()->doLogout($state["\SimpleSAML\Auth\Source.id"]);
                }
                \SimpleSAML\Module\saml\IdP\SAML2::handleAuthError(
                    new \SimpleSAML\Module\saml\Error(
                        \SAML2\Constants::STATUS_RESPONDER,
                        \SAML2\Constants::STATUS_AUTHN_FAILED,
                        "Authentication cancelled by user"
                    ),
                    $state
                );
            }
        ',
    ),
);

$metadata['https://ssp.dev.openconext.local/simplesaml/saml2/idp/metadata.php'] = array(
    'host' => 'ssp.dev.openconext.local',

    'signature.algorithm' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',

    /* X.509 key and certificate. Relative to the cert directory. */
    'privatekey' => 'idp.key',
    'certificate' => 'idp.crt',

    /*
     * Authentication source to use. Must be one that is configured in
     * 'config/authsources.php'.
     */
    'auth' => 'example-userpass',

	// Sign logout request and logout responses 
	'redirect.sign' => TRUE,

	// Require validate signature on requests
	'redirect.validate' => FALSE,

	// Sign response
	'saml20.sign.response' => FALSE,

	// Sign assertion
	'saml20.sign.assertion' => TRUE,

	// No artifact binding support
	'saml20.sendartifact' => FALSE,

    //'signature.algorithm' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',

    'attributes.NameFormat' => 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',

    // Use (or 'select') an unspecified NameID. The NameID is generated in the authproc below.
    // This is the NameID that will be but in the Subject of the SAML Assertion
    'NameIDFormat' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified',

    // Authproc to make the output of this IdP sufficiently like OpenConext engineblock to allow
    // the OpenConext Stepup-Gateway to work. When the Stepup Gateway-Gateway is used as a Stepup proxy, it
    // requires an IdP proxy (i.e. engineblock) to work with more than one IdP.
    // The IdP proxy must pass the ID of the user to the Stepup-Gateway along with the attributes for the SP.    

    //'authproc' => $subject_method1_authproc,
    // The cancel filter runs first, before the AttributeMap renames "uid" (see above)
    'authproc' => array_merge($cancel_authproc, $subject_method2_authproc, $attribute_authority_authproc),

    // Required because the eduPersonTargetedID is a "complex" attribute and not a simple string value.
    'attributeencodings' => array(
        'urn:mace:dir:attribute-def:eduPersonTargetedID' => 'raw'
    ),

);


////////////////////////////////////////////////////////////////////////////////
// The "EntraID mock" IdP.
//
// This second hosted IdP emulates an EntraID (AzureAD) IdP for the Stepup-AzureMFA GSSP
// It is served under a second base path (/entra-mock/) under the same hostname.
//
// Properties:
// - No SSO: the IdP uses its own authsource ('entra-mock-userpass', see authsources.php),
//   and the AzureMFA SP is configured with ForceAuthn in saml20-sp-remote.php, so every
//   AuthnRequest at this IdP results in its login page.
// - No Subject handling is required: EntraID-style AuthnRequests (institutions with
//   is_azure_ad: true) carry no Subject at all and SimpleSAMLphp does not need one.
//   ADFS-style AuthnRequests that do carry a Subject are simply ignored.
// - AuthnRequests from the AzureMFA SP are signed; a valid signature is required and is
//   validated against the certificate configured for that SP in saml20-sp-remote.php.
// - Assertions are signed with entra-mock-idp.key; AzureMFA validates them against
//   entra-mock-idp.crt. These key/cert files are not part of this repository, they are
//   provided in the cert dir (mounted from OpenConext-devconf/stepup/ssp in devconf).
//
// The institutions.yaml of the AzureMFA service in OpenConext-devconf should point to:
//   entity_id:    https://ssp.dev.openconext.local/entra-mock/saml2/idp/metadata.php
//   sso_location: https://ssp.dev.openconext.local/entra-mock/module.php/saml/idp/singleSignOnService
//   certificates: [ entra-mock-idp.crt ]
//   is_azure_ad:  true

$entra_mock_authproc = array(

    // Generate an emailAddress NameID with the userPrincipalName (UPN) of the user, like EntraID
    // does. The eduPersonPrincipalName of the account (uid@schacHomeOrganization, e.g.
    // jane-a1@institution-a.example.com) is used as UPN. This NameID deliberately differs from
    // the urn:collab:person:... NameID of the main IdP and from the mail attribute, so that an
    // SP that wrongly uses the NameID instead of the mail attribute breaks.
    1 => array(
        'class' => 'saml:AttributeNameID',
        'identifyingAttribute' => 'eduPersonPrincipalName',
        'Format' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',

        // Don't add NameQualifier and SPNameQualifier attributes to the generated NameID
        'NameQualifier' => FALSE,
        'SPNameQualifier' => FALSE,
    ),
);

// The rest of the mock authproc, merged after the shared $cancel_authproc below, so that
// the cancel filter runs before the AttributeMap renames "uid" (see $cancel_authproc).
$entra_mock_authproc_rest = array(

    // Remove the NameID attribute from the attributes, so that it is not released as an
    // attribute (it would be renamed to internal-collabPersonId by the AttributeMap below)
    5 => array(
        'class' => 'core:AttributeAlter',
        'subject' => 'NameID',
        'pattern' => '/.*/',
        '%remove',
    ),

    // Note: the mail attribute of the account is released unchanged. In this setup that means that email addresses
    // look like jane+jane-a1@dev.openconext.local. For registration and authentication this
    // is the email address used during registration; for the GSSP fallback it is the subject
    // (mail) that the SP passes in the GSSP extension.

    // Convert "short" attribute names (uid, mail, ...) to their long urn:mace... equivalent.
    // The map is defined in sspattributemap/Openconext_short_to_urn.php
    10 => array(
        'class' => 'core:AttributeMap',
        'Openconext_short_to_urn'
    ),

    // EntraID marks an MFA authentication with this attribute; AzureMFA requires it in the
    // response for institutions with is_azure_ad: true
    20 => array(
        'class' => 'core:AttributeAdd',
        'http://schemas.microsoft.com/claims/authnmethodsreferences' => array('http://schemas.microsoft.com/claims/multipleauthn'),
    ),
);

$metadata['https://ssp.dev.openconext.local/entra-mock/saml2/idp/metadata.php'] = array(
    // Select this IdP for requests under the /entra-mock/ base path (host + path)
    'host' => 'ssp.dev.openconext.local/entra-mock',

    // Set the endpoints explicitly: 'host' contains a path, so the auto-generated
    // endpoints would end up with the path twice
    'SingleSignOnService' => 'https://ssp.dev.openconext.local/entra-mock/module.php/saml/idp/singleSignOnService',
    'SingleSignOnServiceBinding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
    'SingleLogoutService' => 'https://ssp.dev.openconext.local/entra-mock/module.php/saml/idp/singleLogout',
    'SingleLogoutServiceBinding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',

    'signature.algorithm' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',

    /* X.509 key and certificate of the mock IdP. Relative to the cert directory.
     * These files are not part of this repository; they are provided in the cert dir
     * (mounted from OpenConext-devconf/stepup/ssp in the devconf setup).
     */
    'privatekey' => 'entra-mock-idp.key',
    'certificate' => 'entra-mock-idp.crt',

    // Dedicated authsource: no SSO with the main IdP (see authsources.php)
    'auth' => 'entra-mock-userpass',

    // The AzureMFA SP signs its AuthnRequests; require and validate the signature
    'redirect.validate' => TRUE,
    'validate.authnrequest' => TRUE,

    // Sign the assertion (AzureMFA validates the assertion signature), not the response
    'saml20.sign.response' => FALSE,
    'saml20.sign.assertion' => TRUE,

    // No artifact binding support
    'saml20.sendartifact' => FALSE,

    'attributes.NameFormat' => 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',

    // Use an emailAddress NameID with the UPN. The NameID is generated in the authproc above.
    // This is the NameID that will be put in the Subject of the SAML Assertion, also when the SP
    // requests another NameID format (SimpleSAMLphp then falls back to this format)
    'NameIDFormat' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',

    'authproc' => array_merge($entra_mock_authproc, $cancel_authproc, $entra_mock_authproc_rest, $attribute_authority_authproc),

);

