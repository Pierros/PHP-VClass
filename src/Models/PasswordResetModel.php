<?php

namespace VClass\Models;

use Http\Request;
use Http\Response;
use Mailgun\Mailgun;

class PasswordResetModel{
    
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
        $this->RECAPTCHA_KEY = "6LeVRgoUAAAAAGyT3Ua4ObHb6SqK5TQXqr-wrYGn";
    }
    
        
    /**
     * Perform the necessary actions to send a password reset email
     *
     * @return bool success status
     */
    public function requestPasswordRequest()
    {
        $user_email = strip_tags($this->request->getParameter('email'));
        // These checks may appear redundant since the DB will return false if a user doesn't exist with the given email,
        // but validating the input helps us reduce unnecessary DB queries and block potentially maliciously-crafted  input
        if (empty($user_email)){
            $this->error = "Σφάλμα PRM1: Το πεδίο του email είναι κενό.";
        }
        
        if (!filter_var($user_email, FILTER_VALIDATE_EMAIL)){
            $this->error = "Σφάλμα PRM2: Το πεδίο του επωνύμου είναι άκυρο.";
        }
        
        // Validate the reCaptcha response
        $captcha = $this->request->getParameter('g-recaptcha-response');
        $response=file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$this->RECAPTCHA_KEY}&response={$captcha}&remoteip=".$_SERVER['REMOTE_ADDR']);
        $obj = json_decode($response);
        if($obj->success == false)
        {
            $this->error = "Σφάλμα PRM3: Η εξακρίβωση του reCaptcha απέτυχε.";
        }
        
        // check if an active user exists
        $userModel = new UserModel($this->pdo);
        $user_exists = $userModel->doesUsernameAlreadyExist($user_email, true, 1);
        if (!$user_exists){
            $this->error = 'Σφάλμα PRM4: Δεν υπάρχει ενεργός χρήστης με το email που καταχωρήσατε.';
        }
        else{
            $success = $this->createPasswordRequestTokens($user_email);
            if ($success != false){
                // get the details of the user
                $user_details = $userModel->getUserDestailsByUsername($user_email);
                if ($user_details == false){
                    $this->error = "Σφάλμα PRM5: Τα στοιχεία χρήστη δεν βρέθηκαν.";
                }
                $mg = new Mailgun("key-ce9366b2dd8376a09b8c67a00e9ab9b2");
                $domain = "sandbox31c4780a620345b1822a94fe9f7f5fc7.mailgun.org";
                // Read the confirmation email
                $email_body = file_get_contents(dirname(__DIR__) . '/../resetEmail.txt', FILE_USE_INCLUDE_PATH);
                // Substitute user name and activation code strings 
                $email_body = str_replace('$firstName', $user_details["firstname"], $email_body);
                $email_body = str_replace('$lastName', $user_details["lastname"], $email_body);
                $email_body = str_replace('$resethash', $success["hash"], $email_body);
                $email_body = str_replace('$userid', $user_details["id"], $email_body);

                try {
                    // Send the confirmation email
                    $email_result = $mg->sendMessage($domain, array(
                            'from'=>'VClass postmaster <postmaster@sandbox31c4780a620345b1822a94fe9f7f5fc7.mailgun.org>',
                            'to'=> $user_email,
                            'subject' => 'Password reset link',
                            'text' => $email_body
                        )
                    );
                    if ($email_result->http_response_code == 200){
                        $success = true;
                    }
                    else{
                        // Delete user if the email failed to be sent
                        $result = $userModel->deleteUserById($user_id);
                        $this->error = "Σφάλμα PRM6: Η αποστολή του email επαναφοράς του κωδικού απέτυχε.";
                    }
                    
                }
                catch (Exception $e){
                    $this->error = "Σφάλμα PRM7: Η αποστολή του email επαναφοράς του κωδικού απέτυχε.";
                }
            }
        }
        
        return $this->error;
    }
     
    /**
     * Create and store password reset token in the database
     *
     * @param string $user_email username
     *
     * @return bool success status
     */
    private function createPasswordRequestTokens($user_email)
    {
        // generate a timestamp of the user request and a 
        // random hash for email password reset verification
        $temporary_timestamp = time();
        $user_password_reset_hash = sha1(uniqid(mt_rand(), true));
        // set token (= a random hash string and a timestamp) into database ...
        $sql = "UPDATE users
                SET user_password_reset_hash = :user_password_reset_hash, user_password_reset_timestamp = :user_password_reset_timestamp
                WHERE username = :username LIMIT 1";
        $query = $this->pdo->prepare($sql);
        $query->execute(array(
            ':user_password_reset_hash' => $user_password_reset_hash, ':username' => $user_email,
            ':user_password_reset_timestamp' => $temporary_timestamp
        ));
        // check if exactly one row was successfully changed
        if ($query->rowCount() == 1) {
            return array("timestamp"=>$temporary_timestamp, "hash"=>$user_password_reset_hash);
        }
        // fallback
        $this->error = "Σφάλμα PRM8: Η αποστολή του email επαναφοράς του κωδικού απέτυχε.";
        return false;
    }

    /**
     * Verifies the password reset via the verification hash token which is valid for 60 minutes
     * @param $user_id int the id of the user requesting the password reset
     * @param $verification_code string hash token
     *
     * @return bool success status
     */ 
    public function verifyPasswordReset($user_id, $verification_code){
        // check if user-provided username + verification code combination exists
        $sql = "SELECT id, user_password_reset_timestamp
                FROM users
                WHERE id = :id
                AND user_password_reset_hash = :user_password_reset_hash
                LIMIT 1";
        $query = $this->pdo->prepare($sql);
        $query->execute(array(
            ':user_password_reset_hash' => $verification_code, 
            ':id' => $user_id        
        ));
        // if this user with exactly this verification hash code does NOT exist
        if ($query->rowCount() != 1) {
            $this->error = 'Σφάλμα PRM9: Άκυρος σύνδεσμος επαναφοράς του κωδικού πρόσβασης.';
        }
        else{
            // get result row (as an object)
            $result_user_row = $query->fetch();
            // 3600 seconds are 1 hour
            $timestamp_one_hour_ago = time() - 3600;
            // if password reset request was sent within the last hour (this timeout is for security reasons)
            if ($result_user_row['user_password_reset_timestamp'] < $timestamp_one_hour_ago) {
                // password reset code expired
                $this->error = 'Σφάλμα PRM10: Ο σύνδεσμος επαναφοράς του κωδικού πρόσβασης έχει λήξει.';
            }
        }
        
        return $this->error;
    }
    
    /**
     * Sets the new password
     *
     * @param bool success status
     */
    public function setNewPassword()
    {
        $user_id = $this->request->getParameter('user_id');
        $user_password_new = $this->request->getParameter('new-password');
        $user_password_repeat = $this->request->getParameter('new-password-repeat');
        $user_password_reset_hash = $this->request->getParameter('verification_code');
        // validate the password
        $this->error = PasswordResetModel::validateResetPassword($user_id, $user_password_reset_hash, $user_password_new, $user_password_repeat);
        if (!$this->error) {
            // crypt the password
            $user_password_hash = password_hash($user_password_new, PASSWORD_DEFAULT);
            // write the password to database (as hashed and salted string), reset user_password_reset_hash
            $stored = $this->saveNewUserPassword($user_id, $user_password_hash, $user_password_reset_hash);
            if (!$stored) 
            {
               $this->error = 'Σφάλμα PRM15: H αλλαγή του κωδικού πρόσβασης απέτυχε.';
            }
        }
        
        return $this->error;
    }
    
    /**
     * Validate the password submission
     *
     * @param $user_name
     * @param $user_password_reset_hash
     * @param $user_password_new
     * @param $user_password_repeat
     *
     * @param bool success status
     */
    private static function validateResetPassword($user_id, $user_password_reset_hash, $user_password_new, $user_password_repeat)
    {
        $error = false;
        if (empty($user_password_reset_hash) || empty($user_id)) {
            $error = 'Σφάλμα PRM11: Τα στοιχεία για την δημιουργία νέου κωδικού ειναι ελλιπή.';
        } else if (empty($user_password_new) || empty($user_password_repeat)) {
            $error = 'Σφάλμα PRM12: Συμπληρώστε τα πεδία για τον νέο κωδικό πρόσβασης.';
        } else if ($user_password_new !== $user_password_repeat) {
            $error = 'Σφάλμα PRM13: Τα πεδία του νέου κωδικού πρόσβασης δεν συμφωνουν μεταξύ τους.';
        } else if (strlen($user_password_new) < 6) {
            $error = 'Σφάλμα PRM14: Ο κωδικός πρόσβασης πρέπει να είναι τουλάχιστον 5 χαρακτήρες.';
        }
        
        return $error;
    }
    
    /**
     * Writes the new password to the database
     *
     * @param string $user_id the id of the user
     * @param string $user_password_hash
     * @param string $user_password_reset_hash
     *
     * @return bool
     */
    public function saveNewUserPassword($user_id, $user_password_hash, $user_password_reset_hash)
    {
        $sql = "UPDATE users SET password = :password, user_password_reset_hash = NULL,
                user_password_reset_timestamp = NULL
                WHERE id = :id AND user_password_reset_hash = :user_password_reset_hash
                LIMIT 1";
        $query = $this->pdo->prepare($sql);
        $query->execute(array(
            ':password' => $user_password_hash, 
            ':id' => $user_id,
            ':user_password_reset_hash' => $user_password_reset_hash
        ));
        // if one result exists, return true, else false. Could be written even shorter btw.
        return ($query->rowCount() == 1 ? true : false);
    }
}