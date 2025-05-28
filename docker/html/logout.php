<?php

const SSP_AUTH_COOKIE_NAME = 'SimpleSAMLAuthToken';

$cookie_was_set = false;

// Check if cookie is set
if (isset($_COOKIE[SSP_AUTH_COOKIE_NAME])) {
    $cookie_was_set = true;
    // Send cookie header to unset cookie
    setcookie(SSP_AUTH_COOKIE_NAME, '', time() - 3600); // Expire cookie
}

$message = '';
if ($cookie_was_set) {
    $message = 'Deleted ' . SSP_AUTH_COOKIE_NAME . ' cookie.';
} else {
    $message = SSP_AUTH_COOKIE_NAME . ' was not present.';
}

$message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

echo <<<HTML
<html lang="en">
<body>
    <p>$message</p>
    <p>
    <a href="/">Home</a>
</p>
</body>
</html>
HTML;
