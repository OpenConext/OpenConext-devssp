<?php
/* HTML of the Test SP. Rendered by sp.php, which sets $view: see sp.php for its contents.
 * Plain PHP template: use e() for every value that goes into the HTML. */

if (!isset($view)) {
    http_response_code(404);
    exit;
}

$params = $view['params'];
// Render fields of the login form, each in its own paragraph or together in one
$fields = static function (string ...$names) use ($view, $params): void {
    echo "<p>\n";
    foreach ($names as $name) {
        renderField($view['fields'], $name, $params);
    }
    echo "</p>\n";
};
$auth = $view['authenticated'];
// The SP of this page (?sp=...): logged in or not, and logout, are about this SP.
// The SP in the form ($params['sp']) is the SP to use for the next login.
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
    <label>You are logged in to SP:</label><code><?= e($view['pageSP']) ?></code><br />
    <label>IdP EntityID:</label><code><?= e($auth['idpEntityID']) ?></code><br />
    <form id="logout" action="sp.php" method="get">
        <input type="hidden" name="sp" value="<?= e($view['pageSP']) ?>" />
        <p><button class="logout" type="submit" name="action" value="logout" title="Log out of <?= e($view['pageSP']) ?>. Note that this does not log you out of any Identity Provider.">Logout</button></p>
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
    <label><strong>You are not logged in to SP:</strong></label><code><?= e($view['pageSP']) ?></code><br />
<?php endif ?>

<h3>Login (again)</h3>
<form id="login" action="sp.php" method="get">
    <?php $fields('idp') ?>
    <?php $fields('loa') ?>
    <p>
        <button id="login_2" class="login" type="submit" name="action" value="login" title="Log in to the selected Service Provider with the selected Identity Provider (again)">Login</button>&nbsp;&nbsp;<button type="submit" name="action" value="reset">Reset</button><br />
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
    <?php $fields('sp') ?>
    <?php $fields('signing') ?>
    <?php $fields('key') ?>
    <?php $fields('nameidpolicy') ?>
    <?php $fields('ssobinding') ?>
    <?php $fields('requesterid', 'requesterid2') ?>
    <?php $fields('scopingIDP', 'scopingIDP2') ?>
    <?php $fields('subject') ?>
    <?php $fields('forceauthn') ?>
    <?php $fields('acsurl') ?>
    <?php $fields('emulateadfs') ?>
    <?php $fields('email_extension', 'sho_extension', 'mdui_displayname') ?>
    <p>
        <button id="login_3" class="login" type="submit" name="action" value="login" title="Log in to the selected Service Provider with the selected Identity Provider (again)">Login</button>&nbsp;&nbsp;<button type="submit" name="action" value="reset">Reset</button><br />
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
