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
    'certificate' => 'MIIDvzCCAqegAwIBAgIUDRXOpuFeb9yrf13sW+PaH/TPUGEwDQYJKoZIhvcNAQELBQAwbzELMAkGA1UEBhMCTkwxEDAOBgNVBAgMB1V0cmVjaHQxEDAOBgNVBAcMB1V0cmVjaHQxJzAlBgNVBAoMHkRldmVsb3BtZW50IERvY2tlciBlbnZpcm9ubWVudDETMBEGA1UEAwwKR2F0ZXdheSBTUDAeFw0yMzA1MTcxMjEzMzNaFw0zMzA1MTQxMjEzMzNaMG8xCzAJBgNVBAYTAk5MMRAwDgYDVQQIDAdVdHJlY2h0MRAwDgYDVQQHDAdVdHJlY2h0MScwJQYDVQQKDB5EZXZlbG9wbWVudCBEb2NrZXIgZW52aXJvbm1lbnQxEzARBgNVBAMMCkdhdGV3YXkgU1AwggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQCuXvUFB4dDTgjH1GwSqwO7csaR4P5aU3nM1irGSOSV4fBsE6G6KDPitY3iyg1mEMicq4m/LQKcsWyxAcBq3tgPqcRDh2MzwPdemqLiqilI/3SKDqfLPnhTwwsur2Q7EvUeT8bwWcBg9v8wQDbYBCKBx6135oYYUBSkPW+G2LP/723mhBjVBkVtBstNU8VA0relkonCaHdmk5Df9+zCH9OKQeqqadWuG49J3XBrxikDDM7qnqu20ib5hw7s3loG/rNQzTSKlzwIuiYRXadLO8qc+cDJ3rMlHETNE4fw/vuOvBi/tJgTng0my9JqDc+qT3ETPDtUtdPVKGBZun3JPHS/AgMBAAGjUzBRMB0GA1UdDgQWBBSzaT4pfPJB04SnfggxVWTMGasNWTAfBgNVHSMEGDAWgBSzaT4pfPJB04SnfggxVWTMGasNWTAPBgNVHRMBAf8EBTADAQH/MA0GCSqGSIb3DQEBCwUAA4IBAQA+Lbu2WsqYtcMkZJmZt8B98Dz2VrP0jfXDKxgyAJwin1RKTKoDBoODbbiSB78R95EurYtbFq/YPjGhHFCNwenaXEhUj4xIKbKWmNfVtb9Bi1KwAFzvuVplFpeODCz3rS6w3uQxIS0K1DS3KPhUdE+SMR5tv6xfKYx59wH+8gnTkJi3xSCh6IL+sfGiPHkE4zFCTGQyUnqiD3TBYrHjCkoolPWaywEYffos0qXhwOjsyoqjH/J/2GxuPncxRckn5oNSKysRKF5PmaaZlNZTiO29Qx/Mm1OfdynSbdNmLei5y3TH/+aozPgdcZ5UIbF/ivAtFoOpMYUAyuE0jxeGNPRp'
);
