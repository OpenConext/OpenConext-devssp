<?php

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Building the authentication request of the Test SP (sp.php) from the parameters of its login form


/**
 * Set the php global variables that authsources.php uses to make the configuration of the hosted SP dynamic.
 * Must be called before SimpleSAMLphp loads its configuration.
 *
 * * $GLOBALS['gSP_redirect_sign'] -- Whether to sign the authentication request (true) or not (false)
 * * $GLOBALS['gSP_secondary_key'] -- Which key to use to sign the authentication request. Use first key (false)
 *                                    or use the second key (true)
 * * $GLOBALS['gSP_signature_algorithm'] -- Identifier of the signature algorithm to use to sign the authentication
 *                                          request
 * * $GLOBALS['gSP_ProtocolBinding'] -- The ProtocolBinding to put in the authentication request (i.e. the binding that
 *                                      this SP requests the IdP to use for sending back the SAML Response to us)
 * * $GLOBALS['gSP_SSOBinding'] -- The binding to use for sending the authentication request to the IdP
 *
 * @param array $params The parameters of the login form, normalised by normaliseParams()
 */
function applySpGlobals(array $params): void
{
    $GLOBALS['gSP_signature_algorithm'] = SP_SIGNING_ALGORITHMS[$params['signing']];
    $GLOBALS['gSP_redirect_sign'] = $GLOBALS['gSP_signature_algorithm'] !== '';
    $GLOBALS['gSP_secondary_key'] = $params['key'] === 'secondary';
    $GLOBALS['gSP_ProtocolBinding'] = SP_BINDING_POST;
    // The SURF MFA extension for ADFS posts its request (see buildLoginContext())
    $GLOBALS['gSP_SSOBinding'] = $params['emulateadfs'] === 'true' ? SP_BINDING_POST : $params['ssobinding'];
}


/** Whether a form field has a value */
function paramIsSet(array $params, string $name): bool
{
    return ($params[$name] ?? '') !== '';
}


/**
 * Build the state array for SimpleSAML\Auth\Simple::login() from the parameters of the login form
 *
 * @param array $params The parameters of the login form, normalised by normaliseParams()
 * @param array $idpMap $gIDPmap from sp-config.inc
 * @param string $returnURL Where SimpleSAMLphp sends the browser after the login
 * @param string $sp The authsource (i.e. the SP) that is used for the login
 * @param string $debugSpAcsURL Base URL of the ACS of the debugsp module
 */
function buildLoginContext(array $params, array $idpMap, string $returnURL, string $sp, string $debugSpAcsURL): array
{
    $context = array(
        'ReturnTo' => $returnURL,
        'ForceAuthn' => $params['forceauthn'] === 'true',
        'saml:NameIDPolicy' => null,
    );

    // IdP
    if (paramIsSet($params, 'idp')) {
        $context['saml:idp'] = $params['idp'];
    }

    // LOA
    // An IdP can map a LOA to '' (e.g. the local IdP): then no AuthnContextClassRef is requested
    $loa = $idpMap[$params['idp']]['loa'][$params['loa']] ?? '';
    if (strlen($loa) > 0) {
        $context['saml:AuthnContextClassRef'] = $loa;
    }

    // Scoping IdPList
    if (paramIsSet($params, 'scopingIDP')) {
        $context['saml:IDPList'] = array($params['scopingIDP']);
        if (paramIsSet($params, 'scopingIDP2')) {
            $context['saml:IDPList'][] = $params['scopingIDP2'];
        }
    }

    // RequesterID
    if (paramIsSet($params, 'requesterid')) {
        $context['saml:RequesterID'] = array($params['requesterid']);
        if (paramIsSet($params, 'requesterid2')) {
            $context['saml:RequesterID'][] = $params['requesterid2'];
        }
    }

    // NameIDPolicy
    // SimpleSAMLphp 2.x expects the NameIDPolicy as an array with the attributes of the NameIDPolicy element.
    // AllowCreate is set to true, like the default NameIDPolicy that SimpleSAMLphp generates.
    if (paramIsSet($params, 'nameidpolicy')) {
        $context['saml:NameIDPolicy'] = array(
            'Format' => $params['nameidpolicy'],
            'AllowCreate' => true,
        );
    }

    // Subject NameID
    if (paramIsSet($params, 'subject')) {
        $nameId = new \SAML2\XML\saml\NameID();
        $nameId->setValue($params['subject']);
        $nameId->setFormat(\SAML2\Constants::NAMEID_UNSPECIFIED);
        $context['saml:NameID'] = $nameId;
    }

    // AssertionConsumerServiceURL
    if (paramIsSet($params, 'acsurl')) {
        $context['debugsp:AssertionConsumerServiceURL'] = $params['acsurl'];
    }

    // Emulate an authentication request by the SURF MFA extension for ADFS. See: https://github.com/SURFnet/ADFS-MFA-SAML2.0-Extension
    // The second factor only (SFO) endpoint of the Stepup-Gateway (see: https://github.com/OpenConext/Stepup-Gateway) will give a different
    // SAML Response when this is enabled. This option allows testing this behaviour without having to install an ADFS server.
    // The trigger for this behaviour in the stepup-gateway is the presence of the 'Context' and 'AuthMethod' POST variables. This also means that a HTTP-POST
    // binding must be used (applySpGlobals() takes care of that).
    // Additionally the SURF MFA extension for ADFS sets the ACS location in the SAML Request to the URL where the response must be posted.
    // We add some dummy URL parameters to the ACS location for this purpose.
    // Because the actual SAML response is posted in the "_SAMLResponse" parameter (instead of "SAMLResponse") the acs location of debugsp module
    // must be used to validate the response as that will rename the "_SAMLResponse" POST variable back to "SAMLResponse" so that
    // SimpleSAMLphp can then process it normally.
    // For more information See: https://github.com/OpenConext/Stepup-Gateway/blob/main/docs/SFO.md#adfs-mfa-extension
    if ($params['emulateadfs'] === 'true') {
        // Set AuthMethod and mock Context like the SURF MFA extension for ADFS would do
        $context['debugsp:extraPOSTvars'] = array(
            'AuthMethod' => 'ADFS.SCSA',
            'Context' => rtrim(file_get_contents(__DIR__ . '/adfs-sfo-context.txt'), "\r\n"),
        );
        if (!isset($context['debugsp:AssertionConsumerServiceURL'])) {
            // Add some GET request parameters to the ACS URL. The IdP that can work with the ADFS SFO endpoint must use
            // this exact URL so these parameters are returned. We do not actually verify this currently
            $context['debugsp:AssertionConsumerServiceURL'] = $debugSpAcsURL . $sp . '?SAMLRequest=request_that_must_be_kept&Context=context_value_that_must_be_kept';
        }
    }

    // SAML Extensions
    // The SAML2 library uses the 'saml:Extensions' element to hold the extensions which is an array of
    // \SimpleSAML\XML\Chunk objects (\SAML2\XML\Chunk before SimpleSAMLphp 2.5), one for each extension to add.
    // The SSP will then add the extensions to the SAML request by calling setExtensions(array $extensions) : void
    // on the SAML2\AuthnRequest object with the array of Chunk objects.
    $extensionChunks = buildExtensionChunks($params);
    if (count($extensionChunks) > 0) {
        $context['saml:Extensions'] = $extensionChunks;
    }

    return $context;
}


/**
 * The SAML Extensions to add to the AuthnRequest
 *
 * @return \SimpleSAML\XML\Chunk[]
 */
function buildExtensionChunks(array $params): array
{
    $extensionChunks = array();

    // GSSP UserAttributes extension (email and SHO)
    $attributes = array();  // Array of attribute name => attribute value
    if (paramIsSet($params, 'email_extension')) {
        $attributes['urn:mace:dir:attribute-def:mail'] = $params['email_extension'];
    }
    if (paramIsSet($params, 'sho_extension')) {
        $attributes['urn:mace:terena.org:attribute-def:schacHomeOrganization'] = $params['sho_extension'];
    }
    if (count($attributes) > 0) {
        // A DOMDocument to hold the DOM structure and to serve as a factory object for DOMElements
        // This is the method used in the Stepup-saml-bundle:
        // https://github.com/OpenConext/Stepup-saml-bundle/blob/main/src/SAML2/Extensions/GsspUserAttributesChunk.php
        $dom = new DOMDocument('1.0', 'UTF-8');

        $userAttributes = $dom->createElementNS('urn:mace:surf.nl:stepup:gssp-extensions', 'gssp:UserAttributes');
        $userAttributes->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $userAttributes->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xs', 'http://www.w3.org/2001/XMLSchema');

        foreach ($attributes as $attributeName => $attributeValue) {
            $attribute = $dom->createElementNS('urn:oasis:names:tc:SAML:2.0:assertion', 'saml:Attribute');
            $attribute->setAttribute('NameFormat', 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri');
            $attribute->setAttribute('Name', $attributeName);

            $value = $dom->createElementNS('urn:oasis:names:tc:SAML:2.0:assertion', 'saml:AttributeValue', $attributeValue);
            $value->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsi:type', 'xs:string');

            $attribute->appendChild($value);
            $userAttributes->appendChild($attribute);
        }

        $dom->appendChild($userAttributes);
        $extensionChunks[] = new \SimpleSAML\XML\Chunk($userAttributes);
    }

    // mdui:UIInfo extension with a mdui:DisplayName (service name), as sent by e.g. OpenConext
    // EngineBlock when initiating a Stepup callout. The Stepup-Gateway can read the DisplayName
    // and forward it to the GSSP so the GSSP can show which service the user is authenticating for.
    if (paramIsSet($params, 'mdui_displayname')) {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $uiInfo = $dom->createElementNS('urn:oasis:names:tc:SAML:metadata:ui', 'mdui:UIInfo');
        $displayName = $dom->createElementNS('urn:oasis:names:tc:SAML:metadata:ui', 'mdui:DisplayName');
        $displayName->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:lang', 'en');
        $displayName->textContent = $params['mdui_displayname'];
        $uiInfo->appendChild($displayName);
        $dom->appendChild($uiInfo);
        $extensionChunks[] = new \SimpleSAML\XML\Chunk($uiInfo);
    }

    return $extensionChunks;
}


/**
 * Link that logs in directly with the given form parameters: action=login and the non-empty parameters, sorted
 */
function buildDirectLoginLink(array $params, string $pageURL): string
{
    $query = array('action=login');
    foreach ($params as $name => $value) {
        if ($value !== '') {
            $query[] = urlencode($name) . '=' . urlencode($value);
        }
    }
    sort($query);
    return $pageURL . '?' . implode('&', $query);
}
