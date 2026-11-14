<?php

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// The fields of the login form of the Test SP (sp.php)
//
// Each field is described once here. This description is used to:
// - normalise the request parameters (normaliseParams()), so the rest of the code gets valid values
// - render the field (renderField() in sp-utils.inc)
// What a field does in the AuthnRequest is in sp-login.inc
//
// Field properties:
// - type:     'select', 'text' or 'checkbox'. The value of a checked checkbox is 'true', of an unchecked one ''
// - label:    The label in front of the field
// - help:     Hover help text of the label (optional)
// - options:  select: value => display name
// - default:  The value when the parameter is missing. For a select with a default, an unknown value also
//             becomes the default. A select without a default keeps any value (e.g. idp: SimpleSAMLphp checks it)
// - datalist: text: suggested values (optional)
// - list:     text: use the datalist of this other field (optional)
// - id:       HTML id of the field (optional, defaults to the field name)
//
// Requires $gIDPmap from sp-config.inc

/** The signature algorithms for the AuthnRequest: 'signing' value => algorithm identifier ('' is not signed) */
const SP_SIGNING_ALGORITHMS = array(
    'none' => '',
    'rsa-sha1' => 'http://www.w3.org/2000/09/xmldsig#rsa-sha1',
    'rsa-sha256' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
    'rsa-sha384' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha384',
    'rsa-sha512' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha512',
);

const SP_BINDING_REDIRECT = 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect';
const SP_BINDING_POST = 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST';

$gFormFields = array(
    'idp' => array(
        'type' => 'select',
        'label' => 'Identity Provider:',
        'help' => 'Select the IdP to authenticate to',
        'options' => array_map(static fn (array $idp): string => $idp['name'], $gIDPmap),
    ),
    'loa' => array(
        'type' => 'select',
        'label' => 'Request LOA:',
        'help' => 'Select the level of assurance to include in the AuthnContextClassRef in the AuthnRequest. The value depends on the selected IdP and the selected LoA and is only added when the IdP supports it.',
        'options' => array('' => 'None', '1' => '1', '1.5' => '1.5', '2' => '2', '3' => '3'),
        'default' => '',
    ),
    'sp' => array(
        'type' => 'select',
        'label' => 'Service Provider EntityID:',
        'help' => 'Select the EntityID to use for this SP. This value goes into the Issuer in the AuthnRequest.',
        'options' => array('default-sp' => 'default-sp', 'second-sp' => 'second-sp', 'third-sp' => 'third-sp', 'fourth-sp' => 'fourth-sp'),
        'default' => 'default-sp',
    ),
    'signing' => array(
        'type' => 'select',
        'label' => 'Signing:',
        'help' => "Select the signing algorithm the SP must use to sign the AuthnRequest, select 'none' to disable signing.",
        'options' => array_combine(array_keys(SP_SIGNING_ALGORITHMS), array_keys(SP_SIGNING_ALGORITHMS)),
        'default' => 'rsa-sha256',
    ),
    'key' => array(
        'type' => 'select',
        'label' => 'Signing key:',
        'help' => 'Select the key the SP must use for signing the AuthnRequest. The default key is the key that is present in the SP metadata.',
        'options' => array('default' => 'default', 'secondary' => 'secondary'),
        'default' => 'default',
    ),
    'nameidpolicy' => array(
        'type' => 'select',
        'label' => 'NameIDPolicy:',
        'help' => "Select the NameID policy that the SP should request from the IdP. This value is put in the Format attribute of the NameIDPolicy in the AuthnRequest. 'None' uses the simpleSAMLphp default behaviour based on the SP and IdP metadata.",
        'options' => array(
            '' => 'None',
            'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified' => 'unspecified',
            'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent' => 'persistent',
            'urn:oasis:names:tc:SAML:2.0:nameid-format:transient' => 'transient',
        ),
        'default' => '',
    ),
    'ssobinding' => array(
        'type' => 'select',
        'label' => 'SSO Binding:',
        'help' => 'Specify the SAML Binding that the SP must use to send the AuthnRequest to the IdP.',
        'options' => array(SP_BINDING_REDIRECT => 'HTTP-Redirect', SP_BINDING_POST => 'HTTP-POST'),
        'default' => SP_BINDING_REDIRECT,
    ),
    'requesterid' => array(
        'type' => 'text',
        'label' => 'Scoping (RequesterID) 1:',
        'help' => 'Specify up to two SP EntityIDs to put in RequesterID Scoping elements in the AuthnRequest. If left blank no elements are added.',
        'datalist' => array('http://localhost/simplesaml/module.php/saml/sp/metadata.php/pieter-local-test-sp'),
    ),
    'requesterid2' => array(
        'type' => 'text',
        'label' => '2:',
        'list' => 'requesterid',
    ),
    'scopingIDP' => array(
        'type' => 'text',
        'id' => 'scoping_idp',
        'label' => 'Scoping (IDPList) 1:',
        'help' => 'Specify up to two IdP EntityIDs to put in IDPList Scoping elements in the AuthnRequest. If left blank no elements are added.',
        'datalist' => array('https://pieter.aai.surfnet.nl/simplesamlphp/saml2/idp/metadata.php', 'https://idp.surfnet.nl'),
    ),
    'scopingIDP2' => array(
        'type' => 'text',
        'label' => '2:',
        'list' => 'scopingIDP',
    ),
    'subject' => array(
        'type' => 'text',
        'label' => 'Subject:',
        'help' => "Specify the value of a NameID to put in a Subject element in the AuthnRequest. If specified a NameID of type 'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified' is added to the AuthnRequest.",
        'datalist' => array(
            'urn:collab:person:dev.openconext.local:admin',
            'urn:collab:person:institution-a.example.com:jane-a1',
            'urn:collab:person:institution-a.example.com:joe-a1',
            'urn:collab:person:institution-a.example.com:',
            'urn:collab:person:institution-b.example.com:',
            'urn:collab:person:institution-c.example.com:',
            'urn:collab:person:institution-d.example.com:',
            'urn:collab:person:Institution-D.EXAMPLE.COM:',
            'urn:collab:person:institution-e.example.com:',
            'urn:collab:person:institution-f.example.com:',
        ),
    ),
    'forceauthn' => array(
        'type' => 'checkbox',
        'label' => 'Force authentication:',
        'help' => 'If selected ForceAuthn is set to true in the AuthnRequest.',
    ),
    'acsurl' => array(
        'type' => 'text',
        'label' => 'AssertionConsumerServiceURL:',
        // The help text and datalist need the URLs of SimpleSAMLphp: sp.php sets them
        'help' => 'Optionally override the AssertionConsumerServiceURL to specify in the AuthnRequest. Leave blank to use the default ACS location that is generated by SimpleSAMLphp.',
        'datalist' => array(),
    ),
    'emulateadfs' => array(
        'type' => 'checkbox',
        'label' => 'Emulate SFO ADFS extension:',
        'help' => "If selected two extra POST variables 'AuthMethod' and 'Context' are added to the AuthnRequest and the HTTP-POST binding is used. Note: Request LOA and Subject should be specified as well to make a valid SFO request to the Stepup-Gateway",
    ),
    'email_extension' => array(
        'type' => 'text',
        'label' => 'Email Userinfo extension:',
        'help' => 'Specify an email address. If left blank the email extension is not added.',
    ),
    'sho_extension' => array(
        'type' => 'text',
        'label' => 'SHO Userinfo extension:',
        'help' => 'Specify a domain. If left blank the schacHomeOrganization extension is not added.',
        'datalist' => array(
            'institution-a.example.com',
            'institution-b.example.com',
            'institution-c.example.com',
            'institution-d.example.com',
            'Institution-D.EXAMPLE.COM',
            'institution-e.example.com',
            'institution-f.example.com',
        ),
    ),
    'mdui_displayname' => array(
        'type' => 'text',
        'label' => 'Service name (mdui:DisplayName) extension:',
        'help' => 'Specify a service name. If left blank the mdui:UIInfo extension is not added.',
    ),
);


/**
 * The values of the form fields from request parameters: one string value for every field in $fields.
 * Missing parameters get the default, unknown select values the default (when the select has one).
 * Parameters that are not form fields (e.g. 'action') are not included.
 */
function normaliseParams(array $request, array $fields): array
{
    $params = array();
    foreach ($fields as $name => $field) {
        $value = $request[$name] ?? null;
        if (!is_string($value)) {
            $value = null;  // Missing, or not a single value (e.g. name[]=...)
        }
        switch ($field['type']) {
            case 'checkbox':
                $value = $value === 'true' ? 'true' : '';
                break;
            case 'select':
                if (array_key_exists('default', $field) && ($value === null || !array_key_exists($value, $field['options']))) {
                    $value = $field['default'];
                }
                break;
        }
        $params[$name] = $value ?? ($field['default'] ?? '');
    }
    return $params;
}
