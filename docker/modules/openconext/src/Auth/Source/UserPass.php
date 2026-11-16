<?php

declare(strict_types=1);

namespace SimpleSAML\Module\openconext\Auth\Source;

use Exception;
use SimpleSAML\Error;
use SimpleSAML\Module\core\Auth\UserPassBase;
use SimpleSAML\Utils;

/**
 * Username-only authentication source, used by both IdPs of this devssp instance
 * (the main IdP 'example-userpass' and the EntraID mock 'entra-mock-userpass').
 *
 * Like exampleauth:UserPass, with the accounts configured as
 * 'username:password' => attributes entries, but entering the username is
 * sufficient: the password part is ignored, like at a real EntraID login where
 * the user has already been authenticated (MFA) before this page is shown.
 *
 * The special account 'cancel' (submitted by the Cancel button on the login
 * page, see themes/openconext/core/loginuserpass.twig) is handled by the cancel
 * authproc filter in metadata/saml20-idp-hosted.php, which returns a SAML error
 * response (Responder / AuthnFailed) to the SP for it.
 */
class UserPass extends UserPassBase
{
    /**
     * Our users, stored in an associative array keyed by username.
     *
     * @var array<string, array>
     */
    private array $users = [];

    /**
     * Usernames that are not offered in the username list on the login page (option 'datalist.hide').
     * These can still log in.
     *
     * @var list<string>
     */
    private array $hiddenUsernames = [];


    /**
     * Constructor for this authentication source.
     *
     * @param array $info  Information about this authentication source.
     * @param array $config  Configuration for this authentication source.
     */
    public function __construct(array $info, array &$config)
    {
        // Call the parent constructor first, as required by the interface
        parent::__construct($info, $config);

        $hidden = $config['datalist.hide'] ?? [];
        if (!is_array($hidden)) {
            throw new Exception("Option 'datalist.hide' of authentication source " . $this->authId . ' must be an array');
        }
        $this->hiddenUsernames = array_values(array_map('strval', $hidden));

        // Validate and parse our configuration. The class name at key 0 has already
        // been removed by \SimpleSAML\Auth\Source::parseAuthSource().
        foreach ($config as $userpass => $attributes) {
            if (!is_string($userpass) || !str_contains($userpass, ':')) {
                // Not an account entry (e.g. another authsource option), ignore it
                continue;
            }

            $username = explode(':', $userpass, 2)[0];

            $attrUtils = new Utils\Attributes();

            try {
                $attributes = $attrUtils->normalizeAttributesArray($attributes);
            } catch (Exception $e) {
                throw new Exception('Invalid attributes for user ' . $username .
                    ' in authentication source ' . $this->authId . ': ' . $e->getMessage());
            }
            $this->users[$username] = $attributes;
        }
    }


    /**
     * The usernames that can log in, for the datalist on the login page (see the TemplateController of this
     * module). Without the special 'cancel' account, which is used by the Cancel button, and without the
     * usernames in the 'datalist.hide' option.
     *
     * @return list<string>
     */
    public function getUsernames(): array
    {
        $hidden = array_merge(['cancel'], $this->hiddenUsernames);
        return array_values(array_filter(
            array_map('strval', array_keys($this->users)),
            static fn (string $u): bool => !in_array($u, $hidden, true)
        ));
    }


    /**
     * Attempt to log in using only the username; the password is ignored.
     *
     * @param string $username  The username the user wrote.
     * @param string $password  The password the user wrote (ignored, may be empty).
     * @return array  Associative array with the users attributes.
     */
    protected function login(string $username, string $password): array
    {
        if (!array_key_exists($username, $this->users)) {
            throw new Error\Error(Error\ErrorCodes::WRONGUSERPASS);
        }

        return $this->users[$username];
    }
}
