<?php

declare(strict_types=1);

namespace SimpleSAML\Module\openconext;

use SimpleSAML\Auth\Source;
use SimpleSAML\Auth\State;
use SimpleSAML\Module\core\Auth\UserPassBase;
use SimpleSAML\Module\openconext\Auth\Source\UserPass;
use SimpleSAML\XHTML\TemplateControllerInterface;
use Throwable;
use Twig\Environment;

/**
 * Template controller of the openconext theme (configured with 'theme.controller' in config.php).
 *
 * Adds the usernames of the openconext:UserPass authsource to the data of the login page, so the
 * theme's loginuserpass.twig can offer them in a datalist on the username field.
 */
class TemplateController implements TemplateControllerInterface
{
    public function setUpTwig(Environment &$twig): void
    {
    }


    public function display(array &$data): void
    {
        // Only the login page of a UserPassBase authsource has a formURL to core/loginuserpass with an AuthState
        $formURL = $data['formURL'] ?? null;
        if (!is_string($formURL) || !str_contains($formURL, 'core/loginuserpass')) {
            return;
        }
        $query = [];
        parse_str((string) parse_url($formURL, PHP_URL_QUERY), $query);
        $authStateId = $query['AuthState'] ?? null;
        if (!is_string($authStateId)) {
            return;
        }

        try {
            $state = State::loadState($authStateId, UserPassBase::STAGEID, true);
            $source = is_array($state) ? Source::getById($state[UserPassBase::AUTHID] ?? '') : null;
        } catch (Throwable) {
            return; // The login page shows any problem with the state itself
        }
        if ($source instanceof UserPass) {
            $data['usernames'] = $source->getUsernames();
        }
    }
}
