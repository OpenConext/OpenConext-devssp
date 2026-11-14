// Login page of the devssp IdPs (main IdP and EntraID mock): behaviour of the Cancel button.
// (External file because the CSP of SimpleSAMLphp is script-src 'self'.)
ready(function () {
    var cancelButton = document.getElementById("cancel_button");
    if (cancelButton) {
        cancelButton.addEventListener("click", function () {
            // Submit the form as the special "cancel" account, also when the username
            // field is still empty: the cancel authproc filter of the IdP then returns a
            // SAML error response (Responder / AuthnFailed) to the SP, simulating a user
            // cancelling the authentication.
            document.getElementById("username").value = "cancel";
        });
    }
});
