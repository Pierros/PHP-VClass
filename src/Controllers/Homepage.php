<?php

namespace VClass\Controllers;

use Http\Request;
use Http\Response;
use VClass\Templates\FrontendRenderer;
use VClass\Security;

class Homepage
{
    private $request;
    private $response;
    private $renderer;
    private $csrf_token;

    public function __construct(Request $request, Response $response, FrontendRenderer $renderer)
    {
        $this->request = $request;
        $this->response = $response;
        $this->renderer = $renderer;
        $this->csrf_token = \VClass\Security\Csrf::makeToken();
    }

    public function show()
    {
    	$data = [
            'csrf_token' => $this->csrf_token
        ];
        error_log("CSRF token: ".$this->csrf_token);
        $html = $this->renderer->render('Homepage', $data);
        $this->response->setContent($html);
    }
}