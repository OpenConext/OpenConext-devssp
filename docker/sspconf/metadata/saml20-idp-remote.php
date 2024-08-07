<?php

// This file defines remote IdPs. These are the IdPs that the SPs hosted by this SSP instance can use for
// authentication.

////////////////////////////////////////////////////////////////////////////
// Idp remote metadata of the IdP hosted at this instance (ssp.dev.openconext.local), for use by the hosted SPs
// Even though this IdP is hosted by this SSP instance, it is considered remote from the SSP SP's perspective so it needs
// to be defined here as a remote IdP. This hosted IdP is defined in sspconf/metadata/saml20-idp-hosted.php file.

// $GLOBALS['gSP_SSOBinding'] is set from the Test SP.
if (! isset($GLOBALS['gSP_SSOBinding']) )
    $GLOBALS['gSP_SSOBinding'] = 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect';

$metadata['https://ssp.dev.openconext.local/simplesaml/saml2/idp/metadata.php'] = array (
    'entityid' => 'https://ssp.dev.openconext.local/simplesaml/saml2/idp/metadata.php',
    'signature.algorithm' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
    'certificate' => 'idp.crt',
    'SingleSignOnService' =>
        array (
            0 =>
                array (
                    'Binding' => $GLOBALS['gSP_SSOBinding'] ?? 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                    'Location' => 'https://ssp.dev.openconext.local/simplesaml/saml2/idp/SSOService.php',
                ),
        ),
    'name' => array(
        'en' => 'ssp.dev.openconext.local - SSP Test IdP',
        'nl' => 'ssp.dev.openconext.local - SSP Test IdP',
    ),
);


////////////////////////////////////////////////////////////////////////////
// The metadata of the Stepup-Gateway IdP, for use by the hosted SPs

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
                    'Binding' => $GLOBALS['gSP_SSOBinding'] ?? 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                    'Location' => 'https://gateway.dev.openconext.local/authentication/single-sign-on',
                ),
        ),
    'SingleLogoutService' =>
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


////////////////////////////////////////////////////////////////////////////
// The metadata of the Stepup-Gateway SFO (second factor only) IdP, for use by the hosted SPs

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
                    'Binding' => $GLOBALS['gSP_SSOBinding'] ?? 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
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
