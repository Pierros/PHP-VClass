<?php

namespace VClass\Controllers;

use Http\Request;
use Http\Response;
use VClass\Templates\FrontendRenderer;
use VClass\Models\PasswordResetModel;
use VClass\Security;

/**
 * Login Controller
 * Handles user registration
 */
class PasswordReset
{
    private $request;
    private $response;
    private $renderer;
    private $passreset;
    private $csrf_token;

    public function __construct(Request $request, Response $response, FrontendRenderer $renderer, PasswordResetModel $passreset)
    {
        $this->request = $request;
        $this->response = $response;
        $this->renderer = $renderer;
        $this->passreset = $passreset;
        $this->csrf_token = \VClass\Security\Csrf::makeToken();
    }

    
    /**
     * Email a password reset link
     * POST request after form submit
     */
    public function requestPasswordRest()
    {
        $error = $this->passreset->requestPasswordRequest();
          
        if ($error){
            $data = [
                'success' => false,
                'message' => $error,
                'csrf_token' => $this->csrf_token
            ];
        }
        else{
            $data = [
                'success' => true,
                'message' => 'Θα σας αποσταλει ενα email με οδηγιες για το πως να κανετε επαναφορα του κωδικού σας.',
                'csrf_token' => $this->csrf_token
            ];
        }
          
        $json_reply = json_encode($data);
        $this->response->setContent($json_reply);
      }
      
    /**
     * Verify the password request hash code (to show the user the password editing view or not)
     * GET request
     */
    public function verifyPasswordReset()
    {
        $user_id = $this->request->getParameter('y');
        $verification_code = $this->request->getParameter('x');
        $error = $this->passreset->verifyPasswordReset($user_id, $verification_code);
          
        if (!$error){
            $data = [
              'verification_code' => $verification_code,
              'user_id' => $user_id,
              'reset' => true,
                'csrf_token' => $this->csrf_token
           ];
        }
        else{
            error_log($error);
            $data = [
                'error' => true,
                'message' => $error,
                'csrf_token' => $this->csrf_token
            ];
        }
          
        $html = $this->renderer->render('Homepage', $data);
        $this->response->setContent($html);
    }
    
    /**
     * Set the new password
     * This happens while the user is not logged in. 
     * The user is identified via the password reset token from the email, automatically filled into the <form> fields.
     * Then (regardless of result) route user to index page (user will get success/error via feedback message)
     * POST request
     */
    public function setNewPassword()
    {
        $error = $this->passreset->setNewPassword();
        
        if ($error){
            $data = [
                'error' => true,
                'message' => $error,
                'csrf_token' => $this->csrf_token
            ];
        }
        else{
            $data = [
                'error' => false,
                'message' => 'Ο κωδικός προσβασής σας άλλαξε επιτυχώς! Μπορείτε τώρα να συνδεθείτε με τον νέο κωδικό πρόσβασης.',
                'csrf_token' => $this->csrf_token
            ];
        }
        
        $html = $this->renderer->render('Homepage', $data);
        $this->response->setContent($html);
    }
}