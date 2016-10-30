<?php

namespace VClass\Models;

class UserModel{
    private $pdo;
    
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
    /**
     * Checks if a username is already taken
     *
     * @param $user_name string username
     *
     * @return bool
     */
    public function doesUsernameAlreadyExist($user_name, $check_status = false, $status = 1)
    {
        $user_exists = false;
        
        if ($check_status == false){
            $sql = "SELECT id FROM users WHERE username=:username LIMIT 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ":username" => $user_name,
            ]);
        }
        else{
            $sql = "SELECT id FROM users WHERE username=:username AND status = :status LIMIT 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ":username" => $user_name,
                ":status" => $status
            ]);
        }
        
        $result = $stmt->fetchObject();
        if (!empty($result)) {
            $user_exists = true;
        }
        
        return $user_exists;
    }
    
    /**
     * Gets the user's id
     *
     * @param $user_name
     *
     * @return mixed
     */
    public function getUserIdByUsername($user_name)
    {
        $sql = "SELECT id FROM users WHERE username = :username LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array(':username' => $user_name));
        // return one row (we only have one result or nothing)
        return $stmt->fetch()['id'];
    }
    
    /**
     * Deletes a user based on the user_id
     *
     * @param $user_id
     *
     */
    public function deleteUserById($user_id)
    {
        $sql = "DELETE FROM users WHERE id = :id";
        $query = $this->pdo->prepare();
        $query->execute(array(':id' => $user_id));
    }
    
    /**
     * @param $user_email
     *
     * @return array the user details as associative array
     */
    public function getUserDestailsByUsername($username)
    {
        $response = false;
        $sql = "SELECT id, username, password, firstname, lastname, status, role, user_failed_logins, user_last_failed_login 
                FROM users 
                WHERE username = :username LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array('username' => $username));
        $user = $stmt->fetch();
        if ($stmt->rowCount() == 1) {
            $response = $user;
        }
        
        return $response;
    }
}
