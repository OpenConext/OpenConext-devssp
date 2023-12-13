<?php

/**
 * The configuration of SimpleSAMLphp
 */

$httpUtils = new \SimpleSAML\Utils\HTTP();

$config = [

  'baseurlpath' => 'https://ssp.dev.openconext.local/simplesaml/',
  'baseurl' => 'https://dev.ssp.openconext.local/simplesaml/',
    'loggingdir' => null,
    'datadir' => 'data/',
    'tempdir' => '/tmp/simplesaml',
    'certdir' => 'config/cert/',
    'technicalcontact_name' => 'Tech Contact',
    'technicalcontact_email' => 'tech@dev.openconext.local',
    'timezone' => 'Europe/Amsterdam',
    'secretsalt' => 'secret',
    'auth.adminpassword' => 'secret',
    'admin.protectmetadata' => false,
    'admin.checkforupdates' => true,
    'trusted.url.domains' => [],
    'trusted.url.regex' => false,
    'enable.http_post' => false,
    'assertion.allowed_clock_skew' => 180,
    'debug' => [
        'saml' => false,
        'backtraces' => false,
        'validatexml' => false,
    ],
    'showerrors' => true,
    'errorreporting' => false,
    'logging.level' => SimpleSAML\Logger::NOTICE,
    'logging.handler' => 'stderr',
    'logging.handler' => 'file',
    'logging.facility' => defined('LOG_LOCAL5') ? constant('LOG_LOCAL5') : LOG_USER,
    'logging.processname' => 'simplesamlphp',
    'logging.logfile' => 'simplesamlphp.log',
    'statistics.out' => [// Log statistics to the normal log.
    ],
    'proxy' => null,
    'enable.saml20-idp' => true,
    'enable.adfs-idp' => false,
    'module.enable' => [
        'exampleauth' => true,
        'core' => true,
        'admin' => true,
        'saml' => true,
        'DebugSP' => true,
        'saml2debug' => true,
    ],
    'session.duration' => 8 * (60 * 60), // 8 hours.
    'session.datastore.timeout' => (4 * 60 * 60), // 4 hours
    'session.state.timeout' => (60 * 60), // 1 hour
    'session.cookie.name' => 'SimpleSAMLSessionID',
    'session.cookie.lifetime' => 0,
    'session.cookie.path' => '/',
    'session.cookie.domain' => '',
    'session.cookie.secure' => true,
    'session.cookie.samesite' => $httpUtils->canSetSameSiteNone() ? 'None' : null,
    'session.phpsession.cookiename' => 'SimpleSAML',
    'session.phpsession.savepath' => null,
    'session.phpsession.httponly' => true,
    'session.authtoken.cookiename' => 'SimpleSAMLAuthToken',
    'session.rememberme.enable' => false,
    'session.rememberme.checked' => false,
    'session.rememberme.lifetime' => (14 * 86400),
    'memcache_store.servers' => [
        [
            ['hostname' => 'localhost'],
        ],
    ],
    'memcache_store.prefix' => '',
    'memcache_store.expires' => 36 * (60 * 60), // 36 hours.
    'language.available' => [
        'en', 'no', 'nn', 'se', 'da', 'de', 'sv', 'fi', 'es', 'ca', 'fr', 'it', 'nl', 'lb',
        'cs', 'sk', 'sl', 'lt', 'hr', 'hu', 'pl', 'pt', 'pt-br', 'tr', 'ja', 'zh', 'zh-tw',
        'ru', 'et', 'he', 'id', 'sr', 'lv', 'ro', 'eu', 'el', 'af', 'zu', 'xh', 'st',
    ],
    'language.rtl' => ['ar', 'dv', 'fa', 'ur', 'he'],
    'language.default' => 'en',
    'language.parameter.name' => 'language',
    'language.parameter.setcookie' => true,
    'language.cookie.name' => 'language',
    'language.cookie.domain' => '',
    'language.cookie.path' => '/',
    'language.cookie.secure' => true,
    'language.cookie.httponly' => false,
    'language.cookie.lifetime' => (60 * 60 * 24 * 900),
    'language.cookie.samesite' => $httpUtils->canSetSameSiteNone() ? 'None' : null,
    'theme.use' => 'default',
    'template.auto_reload' => false,
    'production' => true,
    'assets' => [
        'caching' => [
            'max_age' => 86400,
            'etag' => false,
        ],
    ],
    'idpdisco.enableremember' => true,
    'idpdisco.rememberchecked' => true,
    'idpdisco.validate' => true,
    'idpdisco.extDiscoveryStorage' => null,
    'idpdisco.layout' => 'dropdown',
    'authproc.idp' => [
        30 => 'core:LanguageAdaptor',

        45 => [
            'class'         => 'core:StatisticsWithAttribute',
            'attributename' => 'realm',
            'type'          => 'saml20-idp-SSO',
        ],
        50 => 'core:AttributeLimit',
        99 => 'core:LanguageAdaptor',
    ],
    'authproc.sp' => [
        90 => 'core:LanguageAdaptor',
    ],
    'metadatadir' => 'config/metadata',

    'metadata.sources' => [
        ['type' => 'flatfile'],
    ],
    'metadata.sign.enable' => false,
    'metadata.sign.privatekey' => null,
    'metadata.sign.privatekey_pass' => null,
    'metadata.sign.certificate' => null,
    'store.type'                    => 'phpsession',
    'store.sql.dsn'                 => 'sqlite:/path/to/sqlitedatabase.sq3',
    'store.sql.username' => null,
    'store.sql.password' => null,
    'store.sql.prefix' => 'SimpleSAMLphp',
    'store.sql.options' => [],
    'store.redis.host' => 'localhost',
    'store.redis.port' => 6379,
    'store.redis.username' => '',
    'store.redis.password' => '',
    'store.redis.prefix' => 'SimpleSAMLphp',
    'store.redis.mastergroup' => 'mymaster',
    'store.redis.sentinels' => [],
];
