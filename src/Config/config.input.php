<?php

return array(
    /**
     * Configuration for: Cookies
     * 1209600 seconds = 2 weeks
     * COOKIE_PATH is the path the cookie is valid on, usually "/" to make it valid on the whole domain.
     * @see http://stackoverflow.com/q/9618217/1114320
     * @see php.net/manual/en/function.setcookie.php
     *
     * COOKIE_DOMAIN: The domain where the cookie is valid for. Usually this does not work with "localhost",
     * ".localhost", "127.0.0.1", or ".127.0.0.1". If so, leave it as empty string, false or null.
     * When using real domains make sure you have a dot (!) in front of the domain, like ".mydomain.com". This is
     * strange, but explained here:
     * @see http://stackoverflow.com/questions/2285010/php-setcookie-domain
     * @see http://stackoverflow.com/questions/1134290/cookies-on-localhost-with-explicit-domain
     * @see http://php.net/manual/en/function.setcookie.php#73107
     *
     * COOKIE_SECURE: If the cookie will be transferred through secured connection(SSL). It's highly recommended to set it to true if you have secured connection.
     * COOKIE_HTTP: If set to true, Cookies that can't be accessed by JS - Highly recommended!
     * SESSION_RUNTIME: How long should a session cookie be valid by seconds, 604800 = 1 week.
     */
    'COOKIE_RUNTIME' => 1209600,
    'COOKIE_PATH' => '/',
    'COOKIE_DOMAIN' => "",
    'COOKIE_SECURE' => false,
    'COOKIE_HTTP' => true,
    'SESSION_RUNTIME' => 604800,
);