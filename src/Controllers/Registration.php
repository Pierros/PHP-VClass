<?php

namespace VClass\Controllers;

use Http\Request;
use Http\Response;
use VClass\Templates\FrontendRenderer;
use VClass\Models\RegistrationModel;
use VClass\Security;

/**
 * Registration Controller
 * Register new user
 */
class Registration
{
    private $request;
    private $response;
    private $renderer;
    private $registration;
    private $csrf_token;

    public function __construct(Request $request, Response $response, FrontendRenderer $renderer, RegistrationModel $registration)
    {
        $this->request = $request;
        $this->response = $response;
        $this->renderer = $renderer;
        $this->registration = $registration;
        $csrf_token = \VClass\Security\Csrf::makeToken();
    }
    
    /**
     * Register page action
     * POST-request after form submit
     */
    public function register()
    {
        
    	$error = $this->registration->addNewUser();  
                
        /*
        if (!$error){
            $data = [
                'name' => $this->request->getParameter('name', $this->request->getParameter('email')), 
                'error' => $error
            ];
        }
        else{
            $data = [
                'name' => $this->request->getParameter('error', $error)
            ];
        }
        
        $html = $this->renderer->render('Admin', $data);
        $this->response->setContent($html);
        */
        if ($error){
            $json_reply = array(
                "success"=>false, 
                "message"=>$error,
                'csrf_token' => $this->csrf_token
            );
        }
        else{
            $json_reply = array(
                "success"=>true, 
                "message"=>"Καλως ήρθες! Συντομα θα λαβετε ενα email για να επιβεβαιωσετε το λογαριασμο σας και να ολοκληρωσετε την διαδικασια εγγραφης.",
                'csrf_token' => $this->csrf_token
            );
        }
        
        $this->response->setContent(json_encode($json_reply));
    }
    
    /**
     * Activate page action
     * GET-request after clicking email link
     */
     public function activate()
     {
         $user_id = $this->request->getParameter('y');
         $activation_code = $this->request->getParameter('x');
         
         $error = $this->registration->activateNewUser($user_id, $activation_code);
         
         if (!$error){
             $data = [
                'message' => 'Η εγγραφή σας ολοκληρώθηκε! Μπορείτε τωρα να συνδεθείτε με το email και τον κωδικο που δώσατε κατά την εγγραφή σας.',
                'error' => false,
                'csrf_token' => $this->csrf_token
             ];
         }
         else{
            $data = [
                'message' => $error,
                'error' => false,
                'csrf_token' => $this->csrf_token
             ];
         }
         
        $html = $this->renderer->render('Homepage', $data);
        $this->response->setContent($html);
     }
}