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
    'authproc' => $subject_method2_authproc,

    // Required because the eduPersonTargetedID is a "complex" attribute and not a simple string value.
    'attributeencodings' => array(
        'urn:mace:dir:attribute-def:eduPersonTargetedID' => 'raw'
    ),

);

