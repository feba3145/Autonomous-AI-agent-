<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/**
 * Define PHP 8.1 tokens
 */
if (!defined('T_READONLY')) {
    define('T_READONLY', 42401);
}

/**
 * Define PHP 8.4/8.5 tokens as int IDs so that:
 * 1) nikic/php-parser (Rector 2.x) does not throw in compatibility_tokens.php.
 * 2) PHP_CodeSniffer's Tokens.php does not define them as strings (e.g. 'PHPCS_T_PUBLIC_SET'),
 *    which would cause "Token T_PUBLIC_SET has ID of type string, should be int".
 * We use negative IDs (-1 … -6) because Php8.php values clash with PHP 8.2 engine tokens.
 * Run for PHP < 8.4 so we preempt PHPCS; on 8.4+ the engine may define them. Must run before
 * php_codesniffer/tests/bootstrap.php (and any Rector/php-parser code).
 */
$phpMajor = (int) PHP_MAJOR_VERSION;
$phpMinor = (int) PHP_MINOR_VERSION;
$needsTokenPolyfill = ($phpMajor < 8) || ($phpMajor === 8 && $phpMinor < 4);
if ($needsTokenPolyfill) {
    $php84Tokens = [
        'T_PROPERTY_C' => -1,
        'T_PUBLIC_SET' => -2,
        'T_PROTECTED_SET' => -3,
        'T_PRIVATE_SET' => -4,
        'T_PIPE' => -5,
        'T_VOID_CAST' => -6,
    ];
    foreach ($php84Tokens as $name => $id) {
        if (!defined($name)) {
            define($name, $id);
        }
    }
}

require_once __DIR__ . '/vendor/squizlabs/php_codesniffer/tests/bootstrap.php';
