<?php

//////////////////////////////////////////////////////////////////////////////////////////////////////
// Utilities for generating HTML

/** Derive a HTML color from a string value
 *
 * The same string value will always generate the same color. The returned color is suitable for use
 * as a background color for text on a light (white) background with dark (black) text.
 *
 * @param string $value The value to fingerprint
 * @return string HTML hexadecimal RGB color format: #RRGGBB
 */
function HTMLColorFingerprint($value)
{
    // We want to generate a color that is suitable to use as a background color on a light (white)
    // background for black text, so we want a to pick color with both a minimum and maximum brightness
    // so there is enough contrast between the back text and the background color, and the coloring is
    // still visible.
    // Pick a random color in the Hue, Saturation and Lightness (HSL) color space
    // with a Lightness value between minL and maxL
    $minL = 50.0 / 100.0; // 0 .. 1
    $maxL = 80.0 / 100.0; // 0 .. 1
    // Since the md5 mixes the bits of $value into the fingerprint that it returns, we arbitrarily cut
    // three 4 digit hex numbers (0..65535) from the md5 fingerprint and scale them to appropriate values
    // for H, S and L
    $hexFingerprint=md5($value); // 32 character hex string
    $h = (1.0 / 65535.0) * intval(substr($hexFingerprint, 0, 4), 16);  // 0 .. 1
    $s = (1.0 / 65535.0) * intval(substr($hexFingerprint, 4, 4), 16); // 0 .. 1
    $l = $minL + $maxL * (1.0-$minL) * (1.0 / 65535.0) * intval(substr($hexFingerprint, 8, 4), 16); // $minLightness .. 100 (%)
    // Convert from HSL to Red, Green and Blue (RGB) color space
    // Source: https://stackoverflow.com/questions/20423641/php-function-to-convert-hsl-to-rgb-or-hex
    $r=$l; $g=$l; $b=$l;
    $v = ($l <= 0.5) ? ($l * (1.0 + $s)) : ($l + $s - $l * $s);
    if ($v > 0){
        $m = $l + $l - $v;
        $sv = ($v - $m ) / $v;
        $h *= 6.0;
        $sextant = floor($h);
        $fract = $h - $sextant;
        $vsf = $v * $sv * $fract;
        $mid1 = $m + $vsf;
        $mid2 = $v - $vsf;
        switch ($sextant) {
            case 0:
                $r = $v; $g = $mid1; $b = $m;
                break;
            case 1:
                $r = $mid2; $g = $v; $b = $m;
                break;
            case 2:
                $r = $m; $g = $v; $b = $mid1;
                break;
            case 3:
                $r = $m; $g = $mid2; $b = $v;
                break;
            case 4:
                $r = $mid1; $g = $m; $b = $v;
                break;
            case 5:
                $r = $v; $g = $m; $b = $mid2;
                break;
        }
    }
    // Convert from RGB values (0 .. 1) to hex (00 .. FF)
    $color='#' .str_pad(dechex((int)($r*256)),2,'0',STR_PAD_LEFT)
        .str_pad(dechex((int)($g*256)),2,'0',STR_PAD_LEFT)
        .str_pad(dechex((int)($b*256)),2,'0',STR_PAD_LEFT);
    return $color;
}


/**
 * Show fields of a SAML Response that was captured by the ACS of the debugsp module
 * (see docker/modules/debugsp/src/Controller/ServiceProvider.php) in a Field / Value table, like the
 * attributes. Empty fields are skipped. Timestamps are shown with their offset to the current time.
 */
function SAMLResponseFieldsToTable(array $samlResponse, array $fields)
{
    echo "<div>\n<table class='saml-fields'>\n<tr><th>Field</th><th>Value</th></tr>\n";
    foreach ($fields as $field) {
        if (!isset($samlResponse[$field]) || $samlResponse[$field] === '') {
            continue;
        }
        $value = $samlResponse[$field];
        $style = SAMLFieldIsFingerprinted($field) ? " style='background-color: " . HTMLColorFingerprint($value) . "'" : '';
        $extra = SAMLTimeOffsetToHTML($field, $value);
        echo "<tr><td>" . htmlentities($field) . "</td><td><code{$style}>" . htmlentities($value) . "</code>{$extra}</td></tr>\n";
    }
    echo "</table>\n</div>\n";
}


/**
 * Whether the value of a SAML Response field gets a background color derived from the value
 * (HTMLColorFingerprint()), so it is easy to spot where the same value is used: IDs and timestamps
 */
function SAMLFieldIsFingerprinted(string $field): bool
{
    return in_array($field, array('ID', 'InResponseTo', 'Assertion ID', 'SessionIndex'), true)
        || preg_match('/(Instant|NotBefore|NotOnOrAfter)$/', $field) === 1;
}


/** For a SAML timestamp field: its offset to the current time, e.g. " (now +5 minutes)". '' for other fields. */
function SAMLTimeOffsetToHTML(string $field, string $value): string
{
    if (!preg_match('/(Instant|NotBefore|NotOnOrAfter)$/', $field)) {
        return '';
    }
    $time = strtotime($value);
    if ($time === false) {
        return '';
    }
    $minutes = (int) round(($time - time()) / 60);
    return '<small>&nbsp;(now ' . ($minutes > 0 ? '+' : '') . $minutes . ' minutes)</small>';
}


/**
 * Show the XML of a SAML Response captured by the debugsp module: pretty-printed for readability, and
 * verbatim (as received) in a collapsed section, because the pretty-printed version is not the signed XML.
 *
 * In the pretty-printed XML, attribute values and element texts that are equal to the value of a
 * fingerprinted field (see SAMLFieldIsFingerprinted()) get the same background color as in the tables.
 * A reference to an ID ("#" + ID, e.g. in ds:Reference/@URI) gets the color of the ID.
 */
function SAMLResponseXMLToHTML(array $samlResponse)
{
    $xml = $samlResponse['xml'];

    $colors = array();  // value => color
    foreach ($samlResponse as $field => $value) {
        if (is_string($value) && $value !== '' && SAMLFieldIsFingerprinted($field)) {
            $colors[$value] = HTMLColorFingerprint($value);
        }
    }
    $highlight = static function (string $escapedValue) use ($colors): string {
        $value = html_entity_decode($escapedValue, ENT_QUOTES | ENT_HTML401);
        $id = str_starts_with($value, '#') ? substr($value, 1) : $value;
        if (!isset($colors[$id])) {
            return $escapedValue;
        }
        return "<span style='background-color: {$colors[$id]}'>{$escapedValue}</span>";
    };

    $doc = new DOMDocument();
    $doc->preserveWhiteSpace = false;
    $doc->formatOutput = true;
    if (@$doc->loadXML($xml)) {
        $html = htmlentities($doc->saveXML($doc->documentElement));
        // Attribute values: name="value". Only whole values match, values with entities (&) are skipped.
        $html = preg_replace_callback(
            '/(=&quot;)([^&]+)(&quot;)/',
            static fn (array $m): string => $m[1] . $highlight($m[2]) . $m[3],
            $html
        );
        // Element text: <element>value</element>
        $html = preg_replace_callback(
            '/(&gt;)([^&\n]+)(&lt;\/)/',
            static fn (array $m): string => $m[1] . $highlight($m[2]) . $m[3],
            $html
        );
        echo "<pre class='saml-xml' id='saml-xml-pretty'>{$html}</pre>\n";
    }
    echo "<details><summary>Verbatim XML (as received)</summary><pre class='saml-xml' id='saml-xml-verbatim'>" . htmlentities($xml) . "</pre></details>\n";
}


function AttributeNameToHTML($attribute) {
    static $oidToName = array(
        'urn:oid:1.3.6.1.4.1.1076.20.40.40.1' => 'collabPersonId, Deprecated!',
        'urn:oid:1.3.6.1.4.1.5923.1.1.1.10' => 'urn:mace:dir:attribute-def:eduPersonTargetedID',
        'urn:oid:2.5.4.4' => 'urn:mace:dir:attribute-def:sn',
        'urn:oid:2.5.4.42' => 'urn:mace:dir:attribute-def:givenName',
        'urn:oid:2.5.4.3' => 'urn:mace:dir:attribute-def:cn',
        'urn:oid:2.16.840.1.113730.3.1.241' => 'urn:mace:dir:attribute-def:displayName',
        'urn:oid:0.9.2342.19200300.100.1.3' => 'urn:mace:dir:attribute-def:mail',
        'urn:oid:1.3.6.1.4.1.25178.1.2.9' => 'urn:mace:terena.org:attribute-def:schacHomeOrganization',
        'urn:oid:1.3.6.1.4.1.25178.1.2.10' => 'urn:mace:terena.org:attribute-def:schacHomeOrganizationType',
        'urn:oid:1.3.6.1.4.1.25178.1.2.14' => 'urn:schac:attribute-def:schacPersonalUniqueCode',
        'urn:oid:1.3.6.1.4.1.5923.1.1.1.1' => 'urn:mace:dir:attribute-def:eduPersonAffiliation',
        'urn:oid:1.3.6.1.4.1.5923.1.1.1.9' => 'urn:mace:dir:attribute-def:eduPersonScopedAffiliation',
        'urn:oid:1.3.6.1.4.1.5923.1.1.1.7' => 'urn:mace:dir:attribute-def:eduPersonEntitlement',
        'urn:oid:1.3.6.1.4.1.5923.1.1.1.6' => 'urn:mace:dir:attribute-def:eduPersonPrincipalName',
        'urn:oid:1.3.6.1.4.1.5923.1.5.1.1' => 'urn:mace:dir:attribute-def:isMemberOf',
        'urn:oid:0.9.2342.19200300.100.1.1' => 'urn:mace:dir:attribute-def:uid',
        'urn:oid:2.16.840.1.113730.3.1.39' => 'urn:mace:dir:attribute-def:preferredLanguage',
        'urn:oid:1.3.6.1.4.1.5923.1.1.1.16' => 'urn:mace:dir:attribute-def:eduPersonORCID',
        'urn:oid:1.3.6.1.4.1.1466.115.121.1.15' => 'Directory String (RFC 4517), Error!'
    );

    static $error = array(
        'urn:oid:1.3.6.1.4.1.1076.20.40.40.1',  // collabPersonId (deprecated)
        'urn:oid:1.3.6.1.4.1.1466.115.121.1.15' // This is an LDAP string type, not an attribute. This was mistakenly used by SURFconext instead of urn:oid:1.3.6.1.4.1.25178.1.2.9 (schacHomeOrganization)
    );
    if (in_array($attribute, $error))
        $color="style='color: red'";
    if (isset($oidToName[$attribute])) {
        $title=$oidToName[$attribute];
        return "<span title='$title' $color>".htmlentities($attribute)."</span>";
    }

    return htmlentities($attribute);
}




function HTML_select($label, $id, $options, $value="", $info="")
{
    $label=htmlentities($label);
    $id=htmlentities($id);
    $value=htmlentities($value);
    echo "<p><label";
    $info=htmlentities($info);
    if (strlen($info)>0)
        echo " title=\"{$info}\"";
    echo ">{$label}</label><select id=\"{$id}\" name=\"{$id}\">";
    foreach ($options as $name => $display) {
        $name=htmlentities($name);
        $display=htmlentities($display);
        echo "<option value=\"{$name}\"";
        if ($name==$value) {
            echo " selected=\"selected\"";
        }
        echo ">{$display}</option>";
    }
    echo "</select></p>";
}


function NameIDArrayToHTML($nameID, $compact=false)
{
    if ($nameID == NULL) {
        echo '<code>NULL</code>';
        return;
    }
    $nameID_array = array();
    $nameID_array['Format'] = $nameID->getFormat();
    $nameID_array['Value'] = $nameID->getValue();

    foreach ($nameID_array as $key => $value) {
        $k = htmlentities($key);
        $v = htmlentities($value);
        $color='';
        if ($k == 'Value') {
            $color=HTMLColorFingerprint($v);
        }
        $labelstyle='';
        if ($compact)
            $labelstyle="style='width: 60px; clear: both'";
        echo "<label $labelstyle>{$k}:</label><code style='background-color: {$color}'>{$v}</code><br />";
    }
}