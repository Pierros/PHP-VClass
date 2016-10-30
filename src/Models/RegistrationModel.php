<?php

namespace VClass\Models;

use Http\Request;
use Http\Response;
use Mailgun\Mailgun;


/**
 * Class RegistrationModel
 *
 * Everything registration-related happens here.
 */
 
// TODO Validate Role
// Create a new Model for handling emails
class RegistrationModel{
    
    private $request;
    private $response;
    private $pdo;
    private $error;

    public function __construct(Request $request, Response $response, \PDO $pdo)
    {
        $this->request = $request;
        $this->response = $response;
        $this->pdo = $pdo;
        $this->error = false;
    }
    
    /**
     * Handles the entire registration process 
     * and creates a new user in the database if everything is fine
     *
     * @return boolean Gives back the success status of the registration
     */
    public function addNewUser()
    {
        $success = false;
        
        // clear input parameters
        $user_email = strip_tags($this->request->getParameter('email'));        
        $password = $this->request->getParameter('password');
        $password_repeat = $this->request->getParameter('password2');
        $first_name = strip_tags($this->request->getParameter('first-name'));
        $last_name = strip_tags($this->request->getParameter('last-name'));
        $dob = strip_tags($this->request->getParameter('signup-dob'));
        $user_role = strip_tags($this->request->getParameter('user_role'));
        
        $valid_input = $this->validateInput($user_email, $password, $password_repeat, $first_name, $last_name, $dob);
        
        if ($valid_input){
            // encrypt the password with the PHP 5.5's password_hash() function, results in a 60 character hash string.
            $user_password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // check if the user exists
            $userModel = new UserModel($this->pdo);
            $user_exists = $userModel->doesUsernameAlreadyExist($user_email);
            if (!$user_exists){
                // generate random hash for email verification (40 char string)
                $user_activation_hash = sha1(uniqid(mt_rand(), true));
                
                // write user data to database
                if (!$this->writeNewUserToDatabase($user_email, $user_password_hash, time(), $user_activation_hash, $first_name, $last_name, $dob, $user_role)) {
                    $this->error = 'Σφάλμα: Η δημιουργία λογαριασμού απέτυχε.';
                }
                else{
                    // get the id of the new user
                    $user_id = $userModel->getUserIdByUsername($user_email);
                    if (!$user_id) {
                        $this->error = 'Σφάλμα: Η δημιουργία λογαριασμού απέτυχε.';
                    }
                    else{
                        // send activation email
                        // Mailgun credentials
                        $mg = new Mailgun("key-ce9366b2dd8376a09b8c67a00e9ab9b2");
                        $domain = "sandbox31c4780a620345b1822a94fe9f7f5fc7.mailgun.org";
                        // Read the confirmation email
                        $email_body = file_get_contents(dirname(__DIR__) . '/../confirmationEmail.txt', FILE_USE_INCLUDE_PATH);
                        // Substitute user name and activation code strings 
                        $email_body = str_replace('$firstName', $first_name, $email_body);
                        $email_body = str_replace('$lastName', $last_name, $email_body);
                        $email_body = str_replace('$activation', $user_activation_hash, $email_body);
                        $email_body = str_replace('$userid', $user_id, $email_body);

                        try {
                            // Send the confirmation email
                            $email_result = $mg->sendMessage($domain, array(
                                    'from'=>'VClass postmaster <postmaster@sandbox31c4780a620345b1822a94fe9f7f5fc7.mailgun.org>',
                                    'to'=> $user_email,
                                    'subject' => 'Please confirm your email',
                                    'text' => $email_body
                                )
                            );
                            if ($email_result->http_response_code == 200){
                                $success = true;
                            }
                            else{
                                // Delete user if the email failed to be sent
                                $result = $userModel->deleteUserById($user_id);
                                $this->error = "Σφάλμα: Η αποστολή email ενεργοποίησης της εγγραφής απέτυχε.";
                            }
                            
                        }
                        catch (Exception $e){
                            // Delete user if the email failed to be sent
                            $result = $userModel->deleteUserById($user_id);
                            $this->error = "Σφάλμα: Η αποστολή του email ενεργοποίησης απέτυχε.";
                        }
                    }
                }
            }
            else{
                $this->error = 'Σφάλμα: Το email έχει καταχωρηθεί ήδη απο άλλον χρήστη.';
            }
        }
        
        if ($success == false && $this->error == false){
            $this->error = "Σφάλμα: Η δημιουργία λογαριασμού απέτυχε.";
        }
        
        return $this->error;
    }
    
    /**
     * Validates the registration input
     *
     * @param $email
     * @param $password
     * @param $password_repeat
     * @param $first_name
     * @param $last_name
     * @param $dob
     * @return bool
     */
    private function validateInput($user_email, $password, $password_repeat, $first_name, $last_name, $dob)
    {
        $valid_password = $this->validateUserPassword($password, $password_repeat);
        $valid_email = $this->validateUserEmail($user_email);
        $valid_name = $this->validateUserName($first_name, $last_name);
        $valid_dob = $this->validateDate($dob);
        
        return $valid_password AND $valid_email AND $valid_name AND $valid_dob;
    }
    
    /**
     * Validates the date of birth
     *
     * @param $date
     * @param $format
     * @return bool
     */
    private function validateDate($date, $format='d/m/Y'){
        $valid = true;
        
        if (empty(trim($date))){
            $this->error = 'Σφάλμα: Το πεδίο της ημερομηνίας γέννησης είναι κενό.';
            $valid = false;
        }
        
        $d = \DateTime::createFromFormat($format, $date);
        $now = new \DateTime();
        
        $valid_format = $d && $d->format($format) == $date;
        if (!$valid_format){
            $this->error = 'Σφάλμα: Ελέγξτε ότι το πεδίο της ημερομηνίας γέννησης έχει το σωστό format.';
            $valid = false;
        }else{
            // Users younger than 10 years old are not allowed 
            $age = $now->diff($d)->days;
            if ($age < 365*10){
                $this->error = 'Σφάλμα: Η εγγραφή επιτρέπεται σε χρήστες ανω των 10 ετών.';
                $valid = false;
            }
        }
        
        return $valid;
    }
    
    /**
     * Validates the first and last name
     *
     * @param $firt_name
     * @param $last_name
     * @return bool
     */
    private function validateUserName($first_name, $last_name){
        $valid = true;
        
        if (empty(trim($first_name))){
            $this->error = "Sf";
            $valid = false;
        }
        
        if (empty(trim($last_name))){
            $this->error = "Σφάλμα: Το πεδίο του επωνύμου είναι κενό.";
            $valid = false;
        }
        
        return $valid;
    }
    
    /**
     * Validates the password
     *
     * @param $password
     * @param $password_repeat
     * @return bool
     */
    private function validateUserPassword($password, $password_repeat)
    {
        $valid = true;
        
        if (empty(trim($password)) OR empty(trim($password_repeat))) {
            $this->error = 'Σφάλμα: Το πεδίο του κωδικού είναι κενό.';
            $valid = false;
        }
        
        if ($password !== $password_repeat) {
            $this->error = 'Σφάλμα: Τα πεδία του κωδικού πρόσβασης είναι διαφορετικά.';
            $valid = false;
        }
        
        if (strlen($password) < 6) {
            $this->error = 'Σφάλμα: Ο κωδικός πρόσβασης πρέπει να είναι τουλάχιστον 6 χαρακτήρες.';
            $valid = false;
        }
        
        return $valid;
    }
    
    /**
     * Validates the email
     *
     * @param $email
     * @return bool
     */
    private function validateUserEmail($email){         
        $valid = true;
        if (empty($email)){
            $valid = false;
            $this->error = "Σφάλμα: Το πεδίο του email είναι κενό.";
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)){
            $valid = false;
            $this->error = "Σφάλμα: Το πεδίο του επωνύμου είναι άκυρο.";
        }
        
        return $valid;
    }
    
    /**
     * Writes the new user's data to the database
     *
     * @param $user_name
     * @param $user_password_hash
     * @param $user_email
     * @param $user_creation_timestamp
     * @param $user_activation_hash
     *
     * @return bool
     */
    public function writeNewUserToDatabase($user_email, $user_password_hash, $user_creation_timestamp, $user_activation_hash, $first_name, $last_name, $dateBirth, $user_role)
    {
        $success = false;
        // write new users data into database
        $sql = "INSERT INTO users (username, password, firstname, lastname, user_creation_timestamp, user_activation_hash, dateBirth, role)
                    VALUES (:username, :password, :firstname, :lastname, :user_creation_timestamp, :user_activation_hash, :dateBirth, :role)";
        $query = $this->pdo->prepare($sql);
        $query->execute(array(':username' => $user_email,
                              ':password' => $user_password_hash,
                              ':firstname' => $first_name,
                              ':user_creation_timestamp' => $user_creation_timestamp,
                              ':user_activation_hash' => $user_activation_hash,
                              ':lastname' => $last_name,
                              ':dateBirth' => $dateBirth,
                              ':role' => $user_role));
        $count =  $query->rowCount();
        if ($count == 1) {
            $success = true;
        }
        return $success;
    }
    
    /**
     * Checks the email verification code and sets the user's activation status to 1 in the database
     * @param $user_id
     * @param $user_activation_code
     *
     & @return bool success status
     */
     public function activateNewUser($user_id, $user_activation_code)
     {
        $sql = "UPDATE users SET status = 1, user_activation_hash = NULL
                WHERE id = :id AND user_activation_hash = :user_activation_hash LIMIT 1";
        $query = $this->pdo->prepare($sql);
        $query->execute(array(':id' => $user_id, ':user_activation_hash' => $user_activation_code));
        if ($query->rowCount() != 1) {
            $this->error = "Ο κωδικός ενεργοποίησης που δώσατε δεν ειναι έγκυρος.";
        }
        
        return $this->error;
     }
}