<?php

namespace VClass\Models;

use Http\Request;
use Http\Response;
use Mailgun\Mailgun;
use VClass\Config;

class LoginModel{
    
    private $request;
    private $response;
    private $pdo;
    private $error;
    private $RECAPTCHA_KEY;
    
    public function __construct(Request $request, Response $response, \PDO $pdo) 
    {
        $this->request = $request;
        $this->response = $response;
        $this->pdo = $pdo;
        $this->error = false;
    }
    
    /**
     * The process to login users in the site
     *
     * @return bool success state
     */
    public function loginUser()
    {
        $user_email = $this->request->getParameter('email');
        $user_password = $this->request->getParameter('password');
        
        if (empty($user_email) OR empty($user_password)) {
            $this->error = "Σφάλμα LM1: Συμπληρώστε τα στοιχεία πρόσβασης και προσπαθήστε ξανά.";
        }
        
        // brute force attack mitigation: use session failed login count and last failed login for not found users.
        // block login attempt if somebody has already failed 3 times and the last login attempt is less than 30sec ago
        // (limits user searches in database)
        if ($_SESSION['failed-login-count'] >= 3 AND ($_SESSION['last-failed-login'] > (time() - 30))) {
            $this->error = 'Σφάλμα LM2: Έχετε αποτύχει 3 συνεχόμενες φορές να συνδεθείτε.';
        }
        
        $userModel = new UserModel($this->pdo);
        $user_details = $userModel->getUserDestailsByUsername($user_email);
        if (!$user_details)
        {
            // increment the number of failed attempts and set the time of last failed attempt
            $_SESSION['failed-login-count'] ++;
            $_SESSION['last-failed-login'] = time();
            
            // although the user doesn't exist just return a generic message to avoid user enumeration
            $this->error = 'Σφάλμα LM3: Λάθος όνομα χρήστη ή κωδικός πρόσβασης.';
        }
        else
        {
            // block login attempt if somebody has already failed 3 times and the last login attempt is less than 30sec ago
            if (($user_details['user_failed_logins'] >= 3) AND ($user_details['user_last_failed_login'] > (time() - 30))) {
                $this->error = 'Σφάλμα LM3: Έχετε αποτύχει 3 συνεχόμενες φορές να συνδεθείτε.';
            }
            // if hash of provided password does NOT match the hash in the database: +1 failed-login counter
            else if (!password_verify($user_password, $user_details['password'])) {
                $result = $this->incrementFailedLoginCounterOfUser($user_details['username']);
                if (!$result){
                    error_log("Could not update the failed login counter for {$user_email}");
                }
                
                $this->error = 'Σφάλμα LM3: Λάθος όνομα χρήστη ή κωδικός πρόσβασης.';
            }
            // check if the user hasn't activated the account yet, or if the account has been de-activated
            else if ($user_details['status'] != 1) {
                $this->error = "Σφάλμα LM4: Ο λογαριασμός δεν έχει ενεργοποιηθεί ακόμα.";
            }
            // login is successful!
            else
            {
                // reset the failed log-in counter for not found users
                $_SESSION['failed-login-count'] = 0;
                $_SESSION['last-failed-login'] = '';
                
                // reset the failed login counter for that user (if necessary)
                if ($user_details['user_last_failed_login'] > 0) {
                    $result = $this->resetFailedLoginCounterOfUser($user_details['username']);
                    if (!$result){
                        error_log("Could not update the failed login counter for {$user_email}");  //log this error for debugging by the server admin but not terminate login process
                    }
                }
                
                // save timestamp of this login in the database record of that user
                $result = $this->saveTimestampOfLoginOfUser($user_email);
                if (!$result){
                    error_log("Could not update the last login timestamp for {$user_email}"); //log this error for debugging by the server admin but not terminate login process
                }
                
                // successfully logged in, so we write all necessary data into the session and set "user_logged_in" to true
                // TODO: check if there was a critical error in setSuccessfulLoginIntoSession that requires roll-back of the login process
                $this->setSuccessfulLoginIntoSession(
                    $user_details['id'], $user_details['username'], $user_details['role']
                );
                
            }
        }
        
        return $this->error;  
    }
    
    /**
     * Increments the failed-login counter of a user
     *
     * @param $user_email
     * @return book success status
     */
    private function incrementFailedLoginCounterOfUser($user_email)
    {
        $response = false;
        $sql = "UPDATE users
                SET user_failed_logins = user_failed_logins+1, user_last_failed_login = :user_last_failed_login
                WHERE username = :username
                LIMIT 1";
        $query = $this->pdo->prepare($sql);
        $query->execute(array(
            ':username' => $user_email, 
            ':user_last_failed_login' => time() 
        ));
        
        // check if exactly one row was successfully changed
        if ($query->rowCount() == 1) {
            $response = true;
        }
        
        return $response;
    }
    
    
    /**
     * Resets the failed-login counter of a user back to 0
     *
     * @param $user_email
     * @return book success status
     */
    private function resetFailedLoginCounterOfUser($user_email)
    {
        $response = false;
        
        $sql = "UPDATE users
                SET user_failed_logins = 0, user_last_failed_login = NULL
                WHERE username = :username
                LIMIT 1";
        
        $query = $this->pdo->prepare($sql);
        $query->execute(array(':username' => $user_email));
        
        // check if exactly one row was successfully changed
        if ($query->rowCount() == 1) {
            $response = true;
        }
        
        return $response;
    }
    
    /**
     * Write timestamp of this login into database (we only write a "real" login via login form into the database,
     * not the session-login on every page request
     *
     * @param $user_name
     * @return bool success status
     */
    private function saveTimestampOfLoginOfUser($user_email)
    {
        $response = false;
        
        $sql = "UPDATE users SET user_last_login_timestamp = :user_last_login_timestamp
                WHERE username = :username LIMIT 1";
        $query = $this->pdo->prepare($sql);
        $query->execute(array(
            ':username' => $user_email, 
            ':user_last_login_timestamp' => time())
        );
        
        // check if exactly one row was successfully changed
        if ($query->rowCount() == 1) {
            $response = true;
        }
        
        return $response;
    }
    
    /**
     * The real login process: The user's data is written into the session.
     * Cheesy name, maybe rename. Also maybe refactoring this, using an array.
     *
     * @param $user_id
     * @param $user_name
     * @param $user_email
     * @param $user_account_type
     */
    private function setSuccessfulLoginIntoSession($user_id, $user_email, $user_account_type)
    {
        // remove old and regenerate session ID.
        // It's important to regenerate session on sensitive actions,
        // and to avoid fixated session.
        // e.g. when a user logs in
        
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_email'] = $user_email;
        $_SESSION['user_account_type'] = $user_account_type;
        
        // finally, set user as logged-in
        $_SESSION['user_logged_in'] = true;
        // update session id in database
        $result = $this->updateSessionId($user_id, session_id());
        if (!$result){
            error_log("Could not update the session_id for {$user_email}"); //log this error for debugging by the server admin but not terminate login process
        }
        // set session cookie setting manually,
        // Why? because you need to explicitly set session expiry, path, domain, secure, and HTTP.
        // @see https://www.owasp.org/index.php/PHP_Security_Cheat_Sheet#Cookies
        //setcookie(session_name(), session_id(), time() + \VClass\Config\Configuration::get('SESSION_RUNTIME'), \VClass\Config\Configuration::get('COOKIE_PATH'),
        //    \VClass\Config\Configuration::get('COOKIE_DOMAIN'), \VClass\Config\Configuration::get('COOKIE_SECURE'), \VClass\Config\Configuration::get('COOKIE_HTTP'));
    }
    
    /**
     * Log out process: delete cookie, delete session
     */
    public function logout()
    {   
        if (isset($_SESSION['user_id'])){
            $user_id = $_SESSION['user_id'];
            session_destroy();
            $result = $this->updateSessionId($user_id);
            
            if (!$result){
                error_log("Could not update the session_id for {$user_id}"); //log this error for debugging by the server admin but not terminate logout process
            }
        }
    }
    
    /**
     * update session id in database
     *
     * @access public
     * @param  string $user_id
     * @param  string $session_id
     * @return string
     */
    private function updateSessionId($user_id, $session_id = null)
    {
        $response = false;
        
        $sql = "UPDATE users SET session_id = :session_id WHERE id = :id";
        $query = $this->pdo->prepare($sql);
        $query->execute(array(':session_id' => $session_id, ":id" => $user_id));
        
        // check if exactly one row was successfully changed
        if ($query->rowCount() == 1) {
            $response = true;
        }
        //error_log(json_encode($query->errorInfo()));
        return $response;
    }
    
}