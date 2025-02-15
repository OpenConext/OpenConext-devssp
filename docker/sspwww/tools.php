<?php

require_once('/var/www/simplesaml/lib/_autoload.php');

function log_error($msg) {
    echo "Error: ".htmlspecialchars($msg)."<br />\n";
}
function log_warning($msg) {
    echo "Warning: ".htmlspecialchars($msg)." <br />\n";
}
function log_info($msg) {
    echo "Info: ".htmlspecialchars($msg)." <br />\n";
}

function CheckBase64AndDecode(string $encoded, string $name) : string | false
{
    $matches = [];
    $result=preg_match('/[^a-zA-Z0-9\/+=]/', $encoded, $matches);
    if (false === $result) {
        log_error("Error checking $name for invalid characters");
        return false;
    }
    if ($result > 0) {
        log_error("Invalid characters in $name. Only a-z, A-Z, 0-9, /, + and = are allowed");
        $offending = array_unique($matches);
        $count = count($offending);
        log_error("$count unique invalid character(s) found");
        foreach ($offending as $char) {
            log_error("Invalid character: "
                . (ord($char) < 32 ? ' (control character)' : '' .'0x'.dechex(ord($char)))
                . '; First occurrence at position: ' . strpos($encoded, $char)
            );
        }

        return false;
    }
    // The base64 encoded string must be a multiple of 4 characters long
    if (strlen($encoded) % 4 != 0) {
        log_error("Invalid length of $name. The length must be a multiple of 4 characters");
        return false;
    }
    // The base64 encoded string must only contain a maximum of two padding characters at the end of the string
    if (preg_match('/={3,}$/', $encoded)) {
        log_error("Invalid padding in $name. Only a maximum of two padding characters are allowed");
        return false;
    }
    $decoded = base64_decode($encoded, true);
    if ($decoded === false) {
        log_error("Base64 decoding of $name failed");
        return false;
    }

    return $decoded;
}


/** Check a SAMLRequest received via HTTP-Redirect binding
* @param $samlRequestURL: the literal URL of the HTTP GET with the SAMLRequest and other optional parameters like Signature and SigAlg and RelayState
* @param $cert: (optional) the X.509 certificate in PEM format to use to verify the signature of the SAMLRequest
*/
function checkSAMLGetRequest(string $samlRequestURL, string $cert)
{
    if (strlen($samlRequestURL) == 0) {
        log_error("No URL provided");
        return;
    }

    log_info("Validating Request URL: $samlRequestURL");

    // We allow the complete URL to be passed in, the path plus query string or just the query string. E.g:
    // - https://example.com/sso?SAMLRequest=...&Signature=...&SigAlg=...&RelayState=...
    // - /sso?SAMLRequest=...&Signature=...&SigAlg=...&RelayState=...
    // - SAMLRequest=...&Signature=...&SigAlg=...&RelayState=...
    // Extract the query parameters and values in their raw form
    // See https://docs.oasis-open.org/security/saml/v2.0/saml-bindings-2.0-os.pdf section 3.4
    // - If "SAMLEncoding" is specified, it must be 'urn:oasis:names:tc:SAML:2.0:bindings:URL-Encoding:DEFLATE' (default)
    //   this is the only encoding we support, it is unusual to see the SAMLEncoding parameter in the wild
    // - The SAMLRequest MUST not include a ds:Signature element, the signature is passed in the Signature parameter

    // First remove the path and host part from $samlRequestURL if present
    $query = $samlRequestURL;
    // Remove anything before the first '?' character
    $pos = strpos($query, '?');
    if ($pos !== false) {
        $query = substr($query, $pos + 1);
    }
    $requestsPVs = [];      // URL-decoded parameter-value pairs
    $requestsPVsRaw = [];   // URL-encoded parameter-value pairs
    // Separate the query string into key-value pairs on '&' character
    $pv = explode('&', $query);
    foreach ($pv as $pair) {
        // Separate the key and value on '=' character
        $kv = explode('=', $pair);
        if (count($kv) != 2) {
            log_error("Error parsing URL parameter-value pair: $pair");
            return;
        }
        if (count($kv) == 2) {
            $requestsPVsRaw[$kv[0]] = $kv[1];
            $parameter = urldecode($kv[0]);
            $value = urldecode($kv[1]);
            log_info("Parameter: $parameter = $value");
            switch ($parameter) {
                case 'SAMLEncoding':
                    if ($value != 'urn:oasis:names:tc:SAML:2.0:bindings:URL-Encoding:DEFLATE') {
                        log_error("Unsupported SAMLEncoding: $value");
                        return;
                    }
                case 'SAMLRequest':
                case 'Signature':
                case 'SigAlg':
                case 'RelayState':
                    // These parameters must only occur once
                    if (isset($requestsPVs[$parameter])) {
                        log_error("Duplicate parameter: $parameter");
                        return;
                    }
                    break;
                default:
                    // Extra parameters are allowed
                    break;
            }
            $requestsPVs[$parameter] = $value;
        }
    }

    if (!isset($requestsPVs['SAMLRequest'])) {
        log_error("No SAMLRequest parameter found");
        return;
    }

    $samlRequest = $requestsPVs['SAMLRequest']; // Base64 encoded and deflate compressed SAMLRequest
    // base64 decode the SAMLRequest
    $samlRequest = CheckBase64AndDecode($samlRequest, 'SAMLRequest');
    if ($samlRequest === false) {
        return;
    }
    log_info("SAMLRequest base64 decoded OK. Length: ".strlen($samlRequest));

    // Inflate the SAMLRequest
    $samlRequest = gzinflate($samlRequest);
    if ($samlRequest === false) {
        log_error("Inflating the SAMLRequest failed");
        return;
    }
    log_info("SAMLRequest inflated OK. Length: ".strlen($samlRequest));

    log_info("SAMLRequest: ".$samlRequest);

    // Use SimpleSAMLphp to parse the SAMLRequest
    try {
        $document = \SAML2\DOMDocumentFactory::fromString($samlRequest);
        if (!$document->firstChild instanceof \DOMElement) {
            log_error("Malformed SAMLRequest received");
            return;
        }
    }
    catch (\Exception $e) {
        log_error("Error parsing SAMLRequest: ".$e->getMessage());
        return;
    }
    log_info("SAMLRequest XML parsed OK");

    // Check to see is the SAMLRequest is signed. A signed SAMLRequest must have a Signature and SigAlg parameter

    // SAMLRequest=value&RelayState=value&SigAlg=value
    // Generate the canonical URL of the SAMLRequest, i.e. the URL without the Signature and SigAlg parameters
    $canonicalURL = '';

    if ( !isset($requestsPVsRaw['Signature']) && isset($requestsPVsRaw['SigAlg'])) {
        log_warning("SigAlg parameter found but no Signature parameter present");
    }
    if ( isset($requestsPVsRaw['Signature']) && !isset($requestsPVsRaw['SigAlg'])) {
        log_warning("Signature parameter found but no SigAlg parameter present");
    }
    if ( isset($requestsPVsRaw['Signature']) && isset($requestsPVsRaw['SigAlg'])) {
        // The SAMLRequest is be signed
        log_info("SAMLRequest is signed");

        $signature = $requestsPVsRaw['Signature'];
        $sigAlg = $requestsPVsRaw['SigAlg'];

        // Build the canonical URL of the SAMLRequest:
        // SAMLRequest=value&RelayState=value&SigAlg=value
        $canonicalURL = 'SAMLRequest='.$requestsPVsRaw['SAMLRequest'];
        if (isset($requestsPVsRaw['RelayState'])) {
            $canonicalURL .= '&RelayState='.$requestsPVsRaw['RelayState'];
        }
        $canonicalURL .= '&SigAlg='.$sigAlg;

        $canonicalURLsha1 = hash('sha1', $canonicalURL);
        $canonicalURLsha256 = hash('sha256', $canonicalURL);

        log_info("Canonicalized string for validating the signature: ".$canonicalURL);
        log_info("Canonicalized string SHA-1 (hex): ". $canonicalURLsha1);
        log_info("Canonicalized string SHA256 (hex): ".$canonicalURLsha256);

        $sigAlg = $requestsPVs['SigAlg'];
        if ($sigAlg != 'http://www.w3.org/2000/09/xmldsig#rsa-sha1' && $sigAlg != 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256') {
            log_info("Unsupported signature algorithm: $sigAlg");
            return;
        }

        if (strlen ($cert) == 0 ) {
            log_info("No certificate provided, unable to verify the signature");
            return;
        }

        // Parse the certificate to get the public key
        // Trim leading and trailing whitespace
        $cert = trim($cert);
        // Check if the certificate is in PEM format
        if (strpos($cert, '-----BEGIN CERTIFICATE-----') === 0) {
            log_info("Certificate is in PEM format");
            $cert .= "\n";
        }
        else {
            // Put the certificate in PEM format
            $cert = "-----BEGIN CERTIFICATE-----\n" . chunk_split($cert, 64, "\n") . "-----END CERTIFICATE-----";
        }

        log_info("Certificate: ".$cert);

        // Use openssl to parse the certificate
        $cert = openssl_x509_read($cert);
        if ($cert === false) {
            log_error("Error decoding the certificate");
            return;
        }
        log_info("Certificate decoded OK");

        // Parse info from the certificate
        $certinfo = openssl_x509_parse($cert);
        if ($certinfo === false) {
            log_error("Error parsing the certificate");
            return;
        }
        log_info("Certificate parsed OK");
        //log_info("Certificate info: ".print_r($certinfo, true));

        $pubkey = openssl_pkey_get_public($cert);
        if ($pubkey === false) {
            log_error("Error extracting the public key from the certificate");
            return;
        }
        log_info("Certificate public key extracted OK");

        // Print the public key type, size and fingerprint
        $keyinfo = openssl_pkey_get_details($pubkey);
        // log_info("Public key: ". print_r($keyinfo, true));

        if ($keyinfo['type'] != OPENSSL_KEYTYPE_RSA) {
            log_error("Unsupported public key type: ".$keyinfo['type']);
            log_error("Only RSA keys are supported");
            return;
        }
        log_info("Certificate public key type: RSA");
        log_info("Certificate public key (i.e. public exponent) size: ".$keyinfo['bits']." bits");
        $n=bin2hex($keyinfo['rsa']['n']);
        $e=bin2hex($keyinfo['rsa']['e']);
        log_info("Certificate public key modulus (n): $n");
        log_info("Certificate public key exponent (e): $e");

        // Decode the signature
        $signature = $requestsPVs['Signature'];
        $signature = CheckBase64AndDecode($signature, 'Signature');
        if ($signature === false) {
            return;
        }
        log_info("Signature base64 decoded OK. Length: ".strlen($signature)." bytes (".strlen($signature)*8 ." bits)");
        log_info("Signature (hex): ".bin2hex($signature));

        // For RSA the signature must be the same length as the modulus
        if (strlen($signature) != $keyinfo['bits'] / 8) {
            log_error("Invalid signature length. The signature length must be the same length as the modulus. I.e. ".($keyinfo['bits'] / 8)." bytes");
            return;
        }
        log_info("Signature length OK");

        // Decode the RSA PKCS#1 signature using the public key
        $decrypted = '';
        // Use openssl_public_decrypt() and not openssl_verify() so we can see the the RAW signature
        $res = openssl_public_decrypt($signature, $decrypted, $pubkey, OPENSSL_PKCS1_PADDING);
        if ($res === false) {
            log_error("Error decrypting the signature using the public key that was provided");
            log_error("Either the value of signature got corrupted or the public key does not match the private key that was used to sign the message");
            return;
        }
        log_info("Signature RSA public decrypt OK");
        log_info("Decrypted signature (hex): ".bin2hex($decrypted));
        $decrypted = strtolower(bin2hex($decrypted));   // Work in hex format
        $sha1ASN1Prefix = '3021300906052b0e03021a05000414'; // ASN.1 prefix for a SHA-1 hash
        $sha256ASN1Prefix = '3031300d060960864801650304020105000420'; // ASN.1 prefix for a SHA-256 hash

        $hash = ''; // hex hash from the signature
        $hashType = ''; // SHA-1 or SHA-256
        if (substr($decrypted, 0, strlen($sha1ASN1Prefix)) == $sha1ASN1Prefix) {
            log_info("The signature contains a SHA-1 hash");
            $hashType = 'sha1';
            $hash = substr($decrypted, strlen($sha1ASN1Prefix));    // Get the SHA-1 hash, must be 20 bytes = 40 hex characters
            log_info("SHA-1 hash from signature (hex): $hash");
            if (strlen($hash) != 40) {
                log_error("Invalid SHA-1 hash length. Expected 20 bytes, got ".(strlen($hash)/2)." bytes");
                return;
            }
        } elseif (substr($decrypted, 0, strlen($sha256ASN1Prefix)) == $sha256ASN1Prefix) {
            log_info("The signature contains a SHA-256 hash");
            $hashType = 'sha256';
            $hash = substr($decrypted, strlen($sha256ASN1Prefix));    // Get the SHA-256 hash, must be 32 bytes = 64 hex characters
            log_info("SHA-256 hash from signature (hex): $hash");
            if (strlen($hash) != 64) {
                log_error("Invalid SHA-256 hash length. Expected 32 bytes, got ".(strlen($hash)/2)." bytes");
                return;
            }
        } else {
            log_error("Invalid signature format. The signature must contain a SHA-1 or SHA-256 hash");
            return;
        }

        // Compare the hash with the SHA-1 or SHA-256 hash of the canonical URL
        if ($hashType == 'sha1') {
            if ($canonicalURLsha1 != $hash) {
                log_error("The SHA-1 hash in the signature does not match the SHA-1 hash of the request URL");
                log_info("Canonicalized URL SHA-1 (hex): $canonicalURLsha1");
                log_info("Signature SHA-1 (hex): $hash");
                return;
            }
            if ($sigAlg != 'http://www.w3.org/2000/09/xmldsig#rsa-sha1') {
                log_error("The signature algorithm does not match the SHA-1 hash type in the signature");
                return;
            }
        }
        if ($hashType == 'sha256') {
            if ($canonicalURLsha256 != $hash) {
                log_error("The SHA-256 hash in the signature does not match the SHA-256 hash of the request URL");
                log_info("Canonicalized URL SHA-256 (hex): $canonicalURLsha256");
                log_info("Signature SHA-256 (hex): $hash");
                return;
            }
            if ($sigAlg != 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256') {
                log_error("The signature algorithm does not match the SHA-256 hash type in the signature");
                return;
            }
        }

        log_info("The signature is valid");
    }

    return;

}

/////////////////////////////////////////////////////////////////

$samlrequest = $_POST['samlrequest'] ?? '';
$samlrequest_html = htmlspecialchars($samlrequest);
$signingcert = $_POST['signingcert'] ?? '';
$signingcert_html = htmlspecialchars($signingcert);

$action = $_POST['action'] ?? '';

echo <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>SAML Tools</title>
</head>
<body>
    <h1>SAML Tools</h1>
    <h2>Check SAML Request via HTTP-Redirect binding</h2>
    <form action="tools.php" method="post">
        <p>
            Enter the SAMLRequest in URL encoded format (i.e. as shown in the URL of your browser or in your web server logs). 
            <br />
            <label for="samlrequest">SAMLRequest</label>
            <textarea name="samlrequest" id="samlrequest" size="100">$samlrequest_html</textarea>
        </p>
        <p>
            Optionally, enter the X.509 certificate to use to verify the signature of the SAMLRequest.
            Provide the certificate either in PEM format or in the form of a base64 encoded X.509 certificate as used
            in SAML metadata.
            <br />
            <label for="signingcert">X.509 Certificate (optional)</label>
            <textarea name="signingcert" id="signingcert" size="100">$signingcert_html</textarea>
        </p>
        <p>
            <input type="submit" name="action" value="check">
        </p>
    </form>
    
HTML;

if ($action == 'check') {
    checkSAMLGetRequest($samlrequest, $signingcert);
}

echo <<<HTML
</body>
</html>
HTML;
