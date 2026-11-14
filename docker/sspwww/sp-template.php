<?php
/* HTML of the Test SP. Rendered by sp.php, which sets $view: see sp.php for its contents.
 * Plain PHP template: use e() for every value that goes into the HTML. */

if (!isset($view)) {
    http_response_code(404);
    exit;
}

$params = $view['params'];
// Value of a form field, for use in the HTML
$field = static fn (string $name, string $default = ''): string => e($params[$name] ?? $default);
$checked = static fn (string $name): string => ($params[$name] ?? '') === 'true' ? ' checked="checked"' : '';
$auth = $view['authenticated'];
$samlResponse = $view['samlResponse'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <link rel="stylesheet" href="sp.css" />
    <title>simpleSAMLphp Test SP</title>
</head>
<body>
<h1>simpleSAMLphp Test SP</h1>

<?php if ($auth !== null): ?>
    <label>You are logged in to SP:</label><code><?= $field('sp', 'default-sp') ?></code><br />
    <label>IdP EntityID:</label><code><?= e($auth['idpEntityID']) ?></code><br />
    <form id="logout" action="sp.php" method="get">
        <p><button class="logout" type="submit" name="action" value="logout">Logout</button></p>
    </form>

    <h3>Session</h3>
    <p>
    <?php if ($auth['sessionIndex'] !== ''): ?>
        <label>SessionIndex:</label><code style="background-color: <?= HTMLColorFingerprint($auth['sessionIndex']) ?>"><?= e($auth['sessionIndex']) ?></code><br />
    <?php endif ?>
        <label>SimpleSAMLphp session start:</label><code><?= e($auth['authnInstant']) ?></code><br />
        <label>SimpleSAMLphp session expire:</label><code><?= e($auth['expire']) ?></code><br />
    </p>

    <h3>LOA</h3>
    <div id="actual_loa"><label>Actual LOA is:</label><code><?= e($auth['actualLOA']) ?></code></div><br />
    <?php if ($auth['requestedLOA'] !== ''): ?>
        <div id="requested_loa"><label>Requested LOA was:</label><code><?= e($auth['requestedLOA']) ?></code></div><br />
    <?php endif ?>

    <h3>NameID</h3>
    <p><?php NameIDArrayToHTML($auth['nameID']) ?></p>

    <h3>Attributes</h3>
    <div>
    <table>
        <tr><th>Attribute</th><th>Value(s)</th></tr>
    <?php foreach ($auth['attributes'] as $attrName => $attrValues): ?>
        <tr><td><?= AttributeNameToHTML($attrName) ?></td><td>
        <?php foreach ((array) $attrValues as $i => $attrValue): ?>
            <?php if ($i > 0): ?><br /><?php endif ?>
            <?php if ($attrValue instanceof \SAML2\XML\saml\NameID): ?>
                <?php NameIDArrayToHTML($attrValue, true) ?>
            <?php else: ?>
                <code style="clear: both"><?= e($attrValue) ?></code>
            <?php endif ?>
        <?php endforeach ?>
        </td></tr>
    <?php endforeach ?>
    </table>
    </div>
<?php else: ?>
    <p><strong>You are not logged in</strong></p>
<?php endif ?>

<h3>Login (again)</h3>
<form id="login" action="sp.php" method="get">
<?php
HTML_select('Identity Provider: ', 'idp', $view['idpOptions'], $params['idp'] ?? '', 'Select the IdP to authenticate to');
HTML_select('Request LOA: ', 'loa',
    array('' => 'None', '1' => '1', '1.5' => '1.5', '2' => '2', '3' => '3'),
    $params['loa'] ?? '',
    'Select the level of assurance to include in the AuthnContextClassRef in the AuthnRequest. The value depends on the selected IdP and the selected LoA and is only added when the IdP supports it.'
);
?>
    <p>
        <button id="login_2" class="login" type="submit" name="action" value="login">Login</button>&nbsp;&nbsp;<button type="submit" name="action" value="reset">Reset</button><br />
    </p>

<?php if ($view['samlResponseIsError']): ?>
    <h3>SAML Error Status</h3>
    <?php SAMLResponseFieldsToTable($samlResponse, array('ID', 'InResponseTo', 'IssueInstant', 'Destination', 'Issuer', 'StatusCode', 'SubStatusCode', 'StatusMessage')) ?>
    <?php /* The XML of the response is shown at the end of the page */ ?>
<?php endif ?>

    <h3>Advanced options</h3>
<?php if ($view['directLoginLink'] !== null): ?>
    <p>
        <button id="update_login_link" type="submit" name="action" value="show">Update direct login link</button><br />
    </p>
    <p><label><a href="<?= e($view['directLoginLink']) ?>">Direct login link</a></label><small><?= e($view['directLoginLink']) ?></small></p><br />
    <p><br /></p>
<?php else: ?>
    <p>
        <button id="show_login_link" type="submit" name="action" value="show">Show direct login link</button><br />
    </p>
<?php endif ?>
<?php
HTML_select('Service Provider EntityID: ', 'sp',
    array('default-sp' => 'default-sp', 'second-sp' => 'second-sp', 'third-sp' => 'third-sp', 'fourth-sp' => 'fourth-sp'),
    $params['sp'] ?? 'default-sp',
    'Select the EntityID to use for this SP. This value goes into the Issuer in the AuthnRequest.'
);
HTML_select('Signing: ', 'signing',
    array('none' => 'none', 'rsa-sha1' => 'rsa-sha1', 'rsa-sha256' => 'rsa-sha256', 'rsa-sha384' => 'rsa-sha384', 'rsa-sha512' => 'rsa-sha512'),
    $params['signing'] ?? 'rsa-sha256',
    "Select the signing algorithm the SP must use to sign the AuthnRequest, select 'none' to disable signing."
);
HTML_select('Signing key: ', 'key',
    array('default' => 'default', 'secondary' => 'secondary'),
    $params['key'] ?? 'default',
    'Select the key the SP must use for signing the AuthnRequest. The default key is the key that is present in the SP metadata.'
);
HTML_select('NameIDPolicy: ', 'nameidpolicy',
    array(
        '' => 'None',
        'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified' => 'unspecified',
        'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent' => 'persistent',
        'urn:oasis:names:tc:SAML:2.0:nameid-format:transient' => 'transient',
    ),
    $params['nameidpolicy'] ?? '',
    "Select the NameID policy that the SP should request from the IdP. This value is put in the Format attribute of the NameIDPolicy in the AuthnRequest. 'None' uses the simpleSAMLphp default behaviour based on the SP and IdP metadata."
);
HTML_select('SSO Binding: ', 'ssobinding',
    array(
        'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect' => 'HTTP-Redirect',
        'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST' => 'HTTP-POST',
    ),
    $params['ssobinding'] ?? 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
    'Specify the SAML Binding that the SP must use to send the AuthnRequest to the IdP.'
);
?>
    <p>
        <label title="Specify up to two SP EntityIDs to put in RequesterID Scoping elements in the AuthnRequest. If left blank no elements are added.">Scoping (RequesterID) 1:</label>
        <input id="requesterid" type="text" name="requesterid" list="commonSPs" value="<?= $field('requesterid') ?>" size="80" /><br />
        <label>2:</label><input type="text" name="requesterid2" list="commonSPs" value="<?= $field('requesterid2') ?>" size="80" /><br />
        <datalist id="commonSPs">
            <option value="http://localhost/simplesaml/module.php/saml/sp/metadata.php/pieter-local-test-sp"></option>
        </datalist>
    </p>
    <p>
        <label title="Specify up to two IdP EntityIDs to put in IDPList Scoping elements in the AuthnRequest. If left blank no elements are added.">Scoping (IDPList) 1:</label>
        <input id="scoping_idp" type="text" name="scopingIDP" list="commonIDPs" value="<?= $field('scopingIDP') ?>" size="80" /><br />
        <label>2:</label><input type="text" name="scopingIDP2" list="commonIDPs" value="<?= $field('scopingIDP2') ?>" size="80" /><br />
        <datalist id="commonIDPs">
            <option value="https://pieter.aai.surfnet.nl/simplesamlphp/saml2/idp/metadata.php"></option>
            <option value="https://idp.surfnet.nl"></option>
        </datalist>
    </p>
    <p>
        <label title="Specify the value of a NameID to put in a Subject element in the AuthnRequest. If specified a NameID of type 'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified' is added to the AuthnRequest.">Subject:</label>
        <input id="subject" type="text" name="subject" list="commonSubjects" value="<?= $field('subject') ?>" size="80" />
        <datalist id="commonSubjects">
            <option value="urn:collab:person:dev.openconext.local:admin"></option>
            <option value="urn:collab:person:institution-a.example.com:jane-a1"></option>
            <option value="urn:collab:person:institution-a.example.com:joe-a1"></option>
            <option value="urn:collab:person:institution-a.example.com:"></option>
            <option value="urn:collab:person:institution-b.example.com:"></option>
            <option value="urn:collab:person:institution-c.example.com:"></option>
            <option value="urn:collab:person:institution-d.example.com:"></option>
            <option value="urn:collab:person:Institution-D.EXAMPLE.COM:"></option>
            <option value="urn:collab:person:institution-e.example.com:"></option>
            <option value="urn:collab:person:institution-f.example.com:"></option>
        </datalist>
    </p>
    <p>
        <label title="If selected ForceAuthn is set to true in the AuthnRequest.">Force authentication:</label>
        <input id="forceauthn" type="checkbox" name="forceauthn" value="true"<?= $checked('forceauthn') ?> /><br />
    </p>
    <p>
        <label title="Optionally override the AssertionConsumerServiceURL to specify in the AuthnRequest. Leave blank to use the default ACS location (<?= e($view['sspAcsURL']) ?>...) that is generated by SimpleSAMLphp.">AssertionConsumerServiceURL:</label>
        <input id="acsurl" type="text" name="acsurl" list="acsurls" value="<?= $field('acsurl') ?>" size="80" />
        <datalist id="acsurls">
            <?php foreach ($view['acsURLs'] as $acsURL): ?>
            <option value="<?= e($acsURL) ?>"></option>
            <?php endforeach ?>
        </datalist>
    </p>
    <p>
        <label title="If selected two extra POST variables 'AuthMethod' and 'Context' are added to the AuthnRequest and the HTTP-POST binding is used. Note: Request LOA and Subject should be specified as well to make a valid SFO request to the Stepup-Gateway">Emulate SFO ADFS extension:</label>
        <input type="checkbox" name="emulateadfs" value="true"<?= $checked('emulateadfs') ?> /><br />
    </p>
    <p>
        <label title="Specify an email address. If left blank the email extension is not added.">Email Userinfo extension:</label>
        <input id="email_extension" type="text" name="email_extension" value="<?= $field('email_extension') ?>" size="80" /><br />
        <label title="Specify a domain. If left blank the schacHomeOrganization extension is not added.">SHO Userinfo extension:</label>
        <input id="sho_extension" type="text" name="sho_extension" list="commonSHOs" value="<?= $field('sho_extension') ?>" size="80" /><br />
        <label title="Specify a service name. If left blank the mdui:UIInfo extension is not added.">Service name (mdui:DisplayName) extension:</label>
        <input id="mdui_displayname" type="text" name="mdui_displayname" value="<?= $field('mdui_displayname') ?>" size="80" /><br />
        <datalist id="commonSHOs">
            <option value="institution-a.example.com"></option>
            <option value="institution-b.example.com"></option>
            <option value="institution-c.example.com"></option>
            <option value="institution-d.example.com"></option>
            <option value="Institution-D.EXAMPLE.COM"></option>
            <option value="institution-e.example.com"></option>
            <option value="institution-f.example.com"></option>
        </datalist>
    </p>
    <p>
        <button id="login_3" class="login" type="submit" name="action" value="login">Login</button>&nbsp;&nbsp;<button type="submit" name="action" value="reset">Reset</button><br />
    </p>
</form>

<?php if ($view['samlResponseIsError']): ?>
    <h3>SAML Response XML</h3>
    <?php SAMLResponseXMLToHTML($samlResponse) ?>
<?php elseif ($auth !== null && $samlResponse !== null): ?>
    <?php /* The last (non error) SAML Response that the SP received, while logged in. This saves a trip to the SAML tracer browser plugin. */ ?>
    <h3>SAML Response</h3>
    <h4>Response</h4>
    <?php SAMLResponseFieldsToTable($samlResponse, array('ID', 'InResponseTo', 'IssueInstant', 'Destination', 'Issuer', 'StatusCode')) ?>
    <h4>Assertion</h4>
    <?php if (($samlResponse['EncryptedAssertion'] ?? '') !== ''): ?>
        <p>The assertion is encrypted.</p>
    <?php else: ?>
        <?php SAMLResponseFieldsToTable($samlResponse, array('Assertion ID', 'Assertion IssueInstant', 'Conditions NotBefore', 'Conditions NotOnOrAfter', 'Audience', 'AuthnInstant', 'SessionNotOnOrAfter', 'SessionIndex')) ?>
    <?php endif ?>
    <h4>SAML Response XML</h4>
    <?php SAMLResponseXMLToHTML($samlResponse) ?>
<?php endif ?>
</body>
</html>
