<?php

namespace VClass\Controllers;

use Http\Request;
use Http\Response;
use VClass\Templates\FrontendRenderer;
use VClass\Models\LoginModel;

/**
 * Login Controller
 * Handles user registration
 */
class Login
{
    private $request;
    private $response;
    private $renderer;
    private $login;
    
    public function __construct(Request $request, Response $response, FrontendRenderer $renderer, LoginModel $login)
    {
        $this->request = $request;
        $this->response = $response;
        $this->renderer = $renderer;
        $this->login = $login;
    }

    /**
     * The login action
     */
    public function login()
    {
        $csrf_token = $this->request->getParameter('token');
        // check if csrf token is valid
        if ($_SESSION['csrf_token'] != $csrf_token OR false) {
            error_log("Received token {$csrf_token} disagrees with session token ".$_SESSION['csrf_token']);
            $_SESSION['csrf_token'] = md5(uniqid(rand(), true));
            $data = [
                'error' => true,
                'message' => 'Η σύνδεση απέτυχε.',
                'token' => $_SESSION['csrf_token']
            ];
            
            $json_reply = json_encode($data);
            $this->response->setContent($json_reply);
        }
        else
        {
            $error = $this->login->loginUser();
            $_SESSION['csrf_token'] = md5(uniqid(rand(), true));
            if ($error){
                $data = [
                    'error' => true,
                    'message' => $error,
                    'token' => $_SESSION['csrf_token']
                ];   
                
                $json_reply = json_encode($data);
                $this->response->setContent($json_reply);
            }
            else
            {
                if ($_SESSION['user_account_type'] == 2)
                {
                    $data = [
                        'error' => false,
                        'url' => '/admin'
                    ];
                }
                else if ($_SESSION['user_account_type'] == 1)
                {
                    $data = [
                        'error' => false,
                        'url' => '/student'
                    ];
                }
                
                $json_reply = json_encode($data);
                $this->response->setContent($json_reply);
            }
        }
    }
    
    /**
     * The logout action
     * Perform logout, redirect user to main-page
     */
    public function logout()
    {
        $this->login->logout();
        
        header("Location: /");
        exit;
    }
    
}