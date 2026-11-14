<?php

/* This is a SAML service provider (web interface) that uses using SimpleSAMLphp for testing authentication
 * The interface allows changing many of the SP configuration parameters
 *
 * - sp-config.inc   The IdPs that can be selected, and their LOAs
 * - sp-fields.inc   The fields of the login form
 * - sp-login.inc    Building the authentication request from the login form
 * - sp-utils.inc    Helpers for generating HTML
 * - sp-template.php The HTML of the page
 */

require_once('sp-config.inc');
require_once('sp-fields.inc');
require_once('sp-login.inc');

// The form fields from the request, with defaults for the missing ones
$request = normaliseParams($_REQUEST, $gFormFields);

// The SP of this page
$sp = $request['sp'];

// Configure the hosted SP (see authsources.php) from the request parameters.
// Must be done before SimpleSAMLphp loads its configuration.
applySpGlobals($request);

// Use SimpleSAMLphp's own bootstrap (not just the autoloader): it also sets the SAML2 container.
// Without it saml2-legacy falls back to its SAML2\Compat\Ssp\Container, which is incomplete
// since SimpleSAMLphp 2.5 (it lacks getInflateSizeLimit()) and causes a fatal error.
require_once('/var/www/simplesaml/public/_include.php');
require_once('sp-utils.inc');


////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Process the HTTP request

$as = new SimpleSAML\Auth\Simple($sp);
$session = SimpleSAML\Session::getSessionFromRequest();

$pageURL = ((($_SERVER['HTTPS'] ?? '') === 'on') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];
// Where SimpleSAMLphp sends the browser after login or logout: this page, without the parameters
// of the login form so we won't trigger a login again (and again, and again, and ...)
$returnURL = $pageURL . '?sp=' . urlencode($sp);

$action = $_REQUEST['action'] ?? '';

// Login and logout do not return
if ($action === 'login') {
    // Save the submitted form in the session, to show it again after the login
    $session->setData('array', 'SSP_DEMO_SP_FORM_DATA', $request);

    $context = buildLoginContext($request, $gIDPmap, $returnURL, $sp, SimpleSAML\Module::getModuleURL('debugsp/acs/'));

    // Store the requested LOA in the session so we can show it after the login
    $session->deleteData('string', 'RequiredAuthnContextClassRef');
    if (isset($context['saml:AuthnContextClassRef'])) {
        $session->setData('string', 'RequiredAuthnContextClassRef', $context['saml:AuthnContextClassRef']);
    }

    $as->login($context);
    exit;
}

if ($action === 'logout') {
    $as->logout(array('ReturnTo' => $returnURL));
    exit;
}

// The values to show in the form
if ($action === 'reset') {
    $session->deleteData('array', 'SSP_DEMO_SP_FORM_DATA');
    // Keep the SP: we may be logged in to it
    $params = normaliseParams(array('sp' => $sp), $gFormFields);
} elseif (isset($_REQUEST['idp'])) {
    // A submitted form
    $params = $request;
} else {
    // The form as it was last submitted, with the parameters of the request (e.g. ?sp=...) on top
    $saved = $session->getData('array', 'SSP_DEMO_SP_FORM_DATA');
    $params = normaliseParams(array_merge(is_array($saved) ? $saved : array(), $_REQUEST), $gFormFields);
}


////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Collect what the page shows

$authenticated = null;
if ($as->isAuthenticated()) {
    $authState = $session->getAuthState($sp);
    $authenticated = array(
        'idpEntityID' => $as->getAuthData('saml:sp:IdP') ?? '',
        'sessionIndex' => $as->getAuthData('saml:sp:SessionIndex') ?? '',
        'authnInstant' => gmdate('r', $authState['AuthnInstant']),
        'expire' => gmdate('r', $authState['Expire']),
        'requestedLOA' => $session->getData('string', 'RequiredAuthnContextClassRef') ?? '',
        'actualLOA' => $authState['saml:sp:AuthnContext'] ?? '',
        'nameID' => $as->getAuthData('saml:sp:NameID'),
        'attributes' => $as->getAttributes(),
    );
}

// The last SAML Response that the SP received, as received. It is stored in the session by the ACS of
// the debugsp module (see docker/modules/debugsp) before SimpleSAMLphp processes it.
$samlResponse = $session->getData('array', 'DEBUGSP_SAML_RESPONSE');
if (!is_array($samlResponse)) {
    $samlResponse = null;
}
// A SAML error response (e.g. the Responder / AuthnFailed response when the user presses Cancel at the
// EntraID mock IdP). The debugsp module's ACS stops SimpleSAMLphp from handling the SAML error itself and
// redirects back here.
$samlResponseIsError = $samlResponse !== null && $samlResponse['StatusCode'] !== ''
    && $samlResponse['StatusCode'] !== 'urn:oasis:names:tc:SAML:2.0:status:Success';
if ($samlResponseIsError) {
    $session->deleteData('array', 'DEBUGSP_SAML_RESPONSE');  // show it only once
}

// The ACS URLs of the SPs, for the AssertionConsumerServiceURL field
$sspAcsURL = SimpleSAML\Module::getModuleURL('saml/sp/saml2-acs.php/');
$debugSpAcsURL = SimpleSAML\Module::getModuleURL('debugsp/acs/');
$gFormFields['acsurl']['help'] = "Optionally override the AssertionConsumerServiceURL to specify in the AuthnRequest. Leave blank to use the default ACS location ({$sspAcsURL}...) that is generated by SimpleSAMLphp.";
$gFormFields['acsurl']['datalist'] = array(
    $sspAcsURL . 'default-sp',
    $debugSpAcsURL . 'default-sp',
    $sspAcsURL . 'second-sp',
    $debugSpAcsURL . 'second-sp?SAMLRequest=request&Context=context',
    $sspAcsURL . 'third-sp',
    $debugSpAcsURL . 'third-sp',
    $sspAcsURL . 'fourth-sp',
    $debugSpAcsURL . 'fourth-sp',
);

$view = array(
    'pageSP' => $sp,                     // The SP of this page (?sp=...): the SP for the login status and logout
    'fields' => $gFormFields,            // The fields of the login form
    'params' => $params,                 // The values of the login form
    'authenticated' => $authenticated,   // null when not logged in
    'samlResponse' => $samlResponse,
    'samlResponseIsError' => $samlResponseIsError,
    'directLoginLink' => $action === 'show' ? buildDirectLoginLink($params, $pageURL) : null,
);

require('sp-template.php');
