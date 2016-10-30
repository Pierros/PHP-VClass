<?php

namespace VClass\Security;

/**
 * Class that protects against Cross Site Request Forgery (CSRF)
 */

class Csrf{
    
    /**
     * Get CSRF token and generate a new one if expired
     *
     * @access public
     * @static static method
     * @return string
     */
    public static function makeToken()
    {
        // token is valid for 1 day
        $max_time    = 60 * 60 * 24;
        $stored_time = 0;
        $csrf_token = null;
        
        if (isset($_SESSION['csrf_token_time']) && isset($_SESSION['csrf_token'])){
            $stored_time = $_SESSION['csrf_token_time'];
            $csrf_token  = $_SESSION['csrf_token'];
        }
        
        if ($max_time + $stored_time <= time() || empty($csrf_token) || $_SESSION['csrf_token'] == 0) {
            $_SESSION['csrf_token'] = md5(uniqid(rand(), true));
            $_SESSION['csrf_token_time'] = time();
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * checks if CSRF token in session is same as in the form submitted
     *
     * @access public
     * @static static method
     * @return bool
     */
    public static function isTokenValid($token)
    {
        return $token === $_SESSION['csrf_token'] && !empty($token);
    }
    
}