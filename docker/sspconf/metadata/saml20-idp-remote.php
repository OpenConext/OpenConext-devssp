<?php

// MANAGED BY ANSIBLE

// This file defines remote IdPs. These are the IdPs that the SPs hosted by this SSP instance can use for
// authentication


////////////////////////////////////////////////////////////////////////////
// Idp remote metadata of the IdP hosted at this instance (ssp.dev.openconext.local), for use by the hosted SPs

$metadata['https://ssp.dev.openconext.local/simplesaml/saml2/idp/metadata.php'] = array (
    'entityid' => 'https://ssp.dev.openconext.local/simplesaml/saml2/idp/metadata.php',
    'signature.algorithm' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
    'certificate' => 'idp.crt',
    'SingleSignOnService' =>
        array (
            0 =>
                array (
                    'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                    'Location' => 'https://ssp.dev.openconext.local/simplesaml/saml2/idp/SSOService.php',
                ),
        ),
    'ArtifactResolutionService' =>
        array (
            0 =>
                array (
                    'index' => 0,
                    'Location' => 'https://ssp.dev.openconext.local/simplesaml/saml2/idp/ArtifactResolutionService.php',
                    'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:SOAP',
                ),
        ),
    'name' => array(
        'en' => 'ssp.dev.openconext.local - SSP Test IdP',
        'nl' => 'ssp.dev.openconext.local - SSP Test IdP',
    ),

);


////////////////////////////////////////////////////////////////////////////
// The metadata of the OpenConext Stepup IdP, for use by the hosted SPs

$metadata['https://gateway.dev.openconext.local/authentication/metadata'] = array (
    'entityid' => 'https://gateway.dev.openconext.local/authentication/metadata',
    //'signature.algorithm' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
    'contacts' =>
        array (
        ),
    'metadata-set' => 'saml20-idp-remote',
    'SingleSignOnService' =>
        array (
            0 =>
                array (
                    'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                    'Location' => 'https://gateway.dev.openconext.local/authentication/single-sign-on',
                ),
        ),
    'SingleLogoutService' =>
        array (
        ),
    'ArtifactResolutionService' =>
        array (
        ),
    'keys' =>
        array (
            0 =>
                array (
                    'encryption' => false,
                    'signing' => true,
                    'type' => 'X509Certificate',
                    'X509Certificate' => 'MIIDwTCCAqmgAwIBAgIUYuSUugwc4J4NyW9WGqYJ/liwM4owDQYJKoZIhvcNAQELBQAwcDELMAkGA1UEBhMCTkwxEDAOBgNVBAgMB1V0cmVjaHQxEDAOBgNVBAcMB1V0cmVjaHQxJzAlBgNVBAoMHkRldmVsb3BtZW50IERvY2tlciBlbnZpcm9ubWVudDEUMBIGA1UEAwwLR2F0ZXdheSBJRFAwHhcNMjMwNTE3MTIxNTEyWhcNMzMwNTE0MTIxNTEyWjBwMQswCQYDVQQGEwJOTDEQMA4GA1UECAwHVXRyZWNodDEQMA4GA1UEBwwHVXRyZWNodDEnMCUGA1UECgweRGV2ZWxvcG1lbnQgRG9ja2VyIGVudmlyb25tZW50MRQwEgYDVQQDDAtHYXRld2F5IElEUDCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBAM2ulQVs5WpbJOAf7Cv/VPDTJqbWHVdUxAmdwZJlcNTRKNFVp4aJzQ3dpiyiGghI5odnzU0/BWBoHZFNYPU/OFr/gzn6iJGxL63L9+mFgE8PR9HpkV5TaRnr21+nZ0EXWjDZk9Px0enERicCItTeQzAUJeA0A9miIcK5IKIz/zSBSR3c802SGD/VelUqY7Z2/UJM97cT92L+4Fz+4zhxxoThbPbrR0CweiROIt82grdwg7zf0+b62MOuVtqFh0yPLRAFfLc4LjHuxFUdUvOHVta7x74dwdmHikqfujM10XN+sNns3LDJde2yPWchU6ktq7cjgbYfIW/vzVzafP1Jk40CAwEAAaNTMFEwHQYDVR0OBBYEFGYn6LWRDZa7+YryUncIlwJB2VorMB8GA1UdIwQYMBaAFGYn6LWRDZa7+YryUncIlwJB2VorMA8GA1UdEwEB/wQFMAMBAf8wDQYJKoZIhvcNAQELBQADggEBAJ57lcOF6PWWW56mS2s5gKFImtfRFzlfiyHsF14L7+nQ5NjfOhpU0wRpnTjK91KP0wCwlxzGFXR8yfqfBFJryIV7aDdYPH/RIkwVaNBI0fsD/ozlYb18seieDEGLvQtTlrmc0UNHtWz6FW3L2geM3ENaqpOATl1Ywp4EPML7Dh0CbhhyM8PnPCEsdclouIeP5/B9Swfk3omXehof6bkFbntqA03msFBiW50twkfKeKULcJGXo667hto27KNxZUauqtPbnAGpUQmge8nxSQlN8RPwlvygVM4LVMF9qP9YxloTH0xVNwN4noZUhfMNsKoJ7Hg5Xulaok8oCqmzEiSroEg=',
                ),
        ),
);

if ( isset($_COOKIE['testcookie']) ) {
    $metadata['https://gateway.dev.openconext.local/authentication/metadata']['keys'][0]['X509Certificate'] = depem(file_get_contents('/vagrant/deploy/tests/behat/fixtures/test_public_key.crt'));
}

////////////////////////////////////////////////////////////////////////////
// The metadata of the OpenConext Stepup IdP - SFO, for use by the hosted SPs

$metadata['https://gateway.dev.openconext.local/second-factor-only/metadata'] = array (
    'entityid' => 'https://gateway.dev.openconext.local/second-factor-only/metadata',
    //'signature.algorithm' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
    'contacts' =>
        array (
        ),
    'metadata-set' => 'saml20-idp-remote',
    'SingleSignOnService' =>
        array (
            0 =>
                array (
                    'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                    'Location' => 'https://gateway.dev.openconext.local/second-factor-only/single-sign-on',
                ),
        ),
    'SingleLogoutService' =>
        array (
        ),
    'ArtifactResolutionService' =>
        array (
        ),
    'keys' =>
        array (
            0 =>
                array (
                    'encryption' => false,
                    'signing' => true,
                    'type' => 'X509Certificate',
                    'X509Certificate' => 'MIIDwTCCAqmgAwIBAgIUYuSUugwc4J4NyW9WGqYJ/liwM4owDQYJKoZIhvcNAQELBQAwcDELMAkGA1UEBhMCTkwxEDAOBgNVBAgMB1V0cmVjaHQxEDAOBgNVBAcMB1V0cmVjaHQxJzAlBgNVBAoMHkRldmVsb3BtZW50IERvY2tlciBlbnZpcm9ubWVudDEUMBIGA1UEAwwLR2F0ZXdheSBJRFAwHhcNMjMwNTE3MTIxNTEyWhcNMzMwNTE0MTIxNTEyWjBwMQswCQYDVQQGEwJOTDEQMA4GA1UECAwHVXRyZWNodDEQMA4GA1UEBwwHVXRyZWNodDEnMCUGA1UECgweRGV2ZWxvcG1lbnQgRG9ja2VyIGVudmlyb25tZW50MRQwEgYDVQQDDAtHYXRld2F5IElEUDCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBAM2ulQVs5WpbJOAf7Cv/VPDTJqbWHVdUxAmdwZJlcNTRKNFVp4aJzQ3dpiyiGghI5odnzU0/BWBoHZFNYPU/OFr/gzn6iJGxL63L9+mFgE8PR9HpkV5TaRnr21+nZ0EXWjDZk9Px0enERicCItTeQzAUJeA0A9miIcK5IKIz/zSBSR3c802SGD/VelUqY7Z2/UJM97cT92L+4Fz+4zhxxoThbPbrR0CweiROIt82grdwg7zf0+b62MOuVtqFh0yPLRAFfLc4LjHuxFUdUvOHVta7x74dwdmHikqfujM10XN+sNns3LDJde2yPWchU6ktq7cjgbYfIW/vzVzafP1Jk40CAwEAAaNTMFEwHQYDVR0OBBYEFGYn6LWRDZa7+YryUncIlwJB2VorMB8GA1UdIwQYMBaAFGYn6LWRDZa7+YryUncIlwJB2VorMA8GA1UdEwEB/wQFMAMBAf8wDQYJKoZIhvcNAQELBQADggEBAJ57lcOF6PWWW56mS2s5gKFImtfRFzlfiyHsF14L7+nQ5NjfOhpU0wRpnTjK91KP0wCwlxzGFXR8yfqfBFJryIV7aDdYPH/RIkwVaNBI0fsD/ozlYb18seieDEGLvQtTlrmc0UNHtWz6FW3L2geM3ENaqpOATl1Ywp4EPML7Dh0CbhhyM8PnPCEsdclouIeP5/B9Swfk3omXehof6bkFbntqA03msFBiW50twkfKeKULcJGXo667hto27KNxZUauqtPbnAGpUQmge8nxSQlN8RPwlvygVM4LVMF9qP9YxloTH0xVNwN4noZUhfMNsKoJ7Hg5Xulaok8oCqmzEiSroEg=',
                ),
        ),
);

if ( isset($_COOKIE['testcookie']) ) {
    $metadata['https://gateway.dev.openconext.local/second-factor-only/metadata']['keys'][0]['X509Certificate'] = depem(file_get_contents('/vagrant/deploy/tests/behat/fixtures/test_public_key.crt'));
}

/**
 * Remove the spicing from the certificate, this is a php port of the python (keyczar) implementation that is used
 * in the ninja templates ( return re.sub(r'\s+|(-----(BEGIN|END).*-----)', '', string) )
 */
function depem($input)
{
    return str_replace([
        '-----BEGIN CERTIFICATE-----',
        '-----END CERTIFICATE-----',
        "\r\n",
        "\n",
    ], [
        '',
        '',
        "\n",
        ''
    ], $input);
}
