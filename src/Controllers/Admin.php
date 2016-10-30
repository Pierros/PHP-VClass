<?php

namespace VClass\Controllers;

use Http\Request;
use Http\Response;
use VClass\Templates\FrontendRenderer;
use VClass\Models\AdminModel;


class Admin
{
    private $request;
    private $response;
    private $renderer;
    private $administrator;
    private $pageReader;

    public function __construct(Request $request, Response $response, FrontendRenderer $renderer, AdminModel $administrator)
    {
        $this->request = $request;
        $this->response = $response;
        $this->renderer = $renderer;
        $this->administrator = $administrator;
    }

    private function check_session(){
        if (!isset($_SESSION['user_email']) || 
            !isset($_SESSION['user_id']) || 
            !isset($_SESSION['user_account_type']) ||
            $_SESSION['user_account_type'] != 2)
        {
            session_destroy();
            header("Location: /");
            exit;
        }
        
        return $_SESSION['user_id'];
    }
    
    /**
     * Receives the requests to display admin pages and processes them appropriately
     * 
     * @param $params the URL parameters
     */
    public function show($params)
    {
        $user_id = $this->check_session();
        
        $data = [
            'username' => $_SESSION['user_email'],
        ];
        
        if (isset($params['slug']))
        {
            $data['function'] = $params['slug'];
            
            if (isset($params['id']))
            {
                if ($params['slug'] == "edit")
                {
                    $data = $this->editCourse($user_id, $params['id']);  
                }
            }
        }
        else{
            $data['function'] = "list";
        }
        
        if ($data['function'] == "list"){
            $data['courses'] = $this->administrator->getCourses($user_id);
        }
            
        $html = $this->renderer->render('Admin', $data);
        $this->response->setContent($html);
    }
    
    /**
     * Handles the request to edit an existing course_id
     *
     * @param $user_id the user id of the teacher
     * @param $course_id the id of the course
     */
    private function editCourse($user_id, $course_id)
    {
        $course_data = $this->administrator->getCourse($user_id, $course_id);
        
        if (!$course_data){
            $data = [
                'username' => $_SESSION['user_email'],
                'function' => 'list',
                'error' => 'Σφάλμα: το μάθημα που ζητήσατε να επεξεργαστείτε δεν βρέθηκε.'
            ];
        }
        else{
            $data = [
                'username' => $_SESSION['user_email'],
                'function' => 'edit',
                'course_data' => $course_data
            ];
        }
        
        return $data;
    }
    
    /**
     * Handles the request to create a new course
     */
    public function addNewCourse()
    {
        $user_id = $this->check_session();
        
        $error = $this->administrator->createNewCourse($user_id);
        
        if ($error){
            $data = [
                'error' => true,
                'message' => $error
            ];
        }
        else{
            $data = [
                'error' => false,
                'message' => 'To μάθημα αποθηκεύτηκε επιτυχώς.'
            ];
        }
        
        $json_reply = json_encode($data);
        $this->response->setContent($json_reply);
    }
    
    /**
     * Updates the data of an existing course
     *
     * @param $params the URL attributes
     */
     public function updateCourse($params)
     {
        $user_id = $this->check_session();
        
        if ($params["slug"] == "update")
        {
            $error = $this->administrator->updateCourse($user_id);
        }
        else if ($params["slug"] == "delete"){
            $error = $this->administrator->deleteCourse($user_id);
        }
        
        if ($error){
            $data = [
                'error' => true,
                'message' => $error
            ];
        }
        else{            
            $data = [
                'error' => false,
                'message' => 'To μάθημα ενημερώθηκε επιτυχώς.'
            ];
            
            $json_reply = json_encode($data);
            $this->response->setContent($json_reply);
        }
     }
    
    /**
     * Manages the administrative requests for displaying test questions
     *
     * @param $params the URL attributes
     */
    public function manageTests($params)
    {
        $user_id = $this->check_session();
        
        //$error = $this->administrator->createNewQuestion($user_id);
        
        $function = "list";
        
        if (isset($params['slug'])){
            $function = $params['slug'];
            
            if (isset($params['id']))
            {
                if ($params['slug'] == "edit"){
                    $data = $this->editQuestion_mc($user_id, $params['id']);
                }
                else if ($params['slug'] == "editbinary")
                {
                    $data = $this->editQuestion_bin($user_id, $params['id']);  
                }
            }
        }
        
        $data['function'] = $function;
        $data['courses'] = $this->administrator->getCourses($user_id);
        
        if ($data['function'] == "list")
        {
            $data['questions'] = $this->administrator->getQuestions_mc($user_id);
            $binary_questions = $this->administrator->getQuestions_bin($user_id);
            $data['questions'] = array_merge($data['questions'], $binary_questions);
        }
        
        $html = $this->renderer->render('Tests', $data);
        $this->response->setContent($html);
    }
    
    /**
     * Handles the request to create a new question
     * @param $user_id the user id of the teacher
     * @param $course_id the id of the course
     */
    public function addNewQuestion()
    {
        $user_id = $this->check_session();
        
        $question_type = $this->request->getParameter('question-type');
        
        if ($question_type == "multi")
            $error = $this->administrator->createNewQuestion($user_id);
        else if ($question_type == "binary")
            $error = $this->administrator->createNewBinaryQuestion($user_id);
        
        if ($error){
            $data = [
                'error' => true,
                'message' => $error
            ];
        }
        else{
            $data = [
                'error' => false,
                'message' => 'Η άσκηση αποθηκεύτηκε επιτυχώς.'
            ];
        }
        
        $json_reply = json_encode($data);
        $this->response->setContent($json_reply);
    }

    /**
     * Handles the request to edit an existing question
     *
     * @param $user_id the user id of the teacher
     * @param $question_id the id of question to edit
     */
    public function editQuestion_mc($user_id, $question_id)
    {
        $question_data = $this->administrator->getQuestions_mc($user_id, $question_id);
        $answer_data = $this->administrator->getAnswers($user_id, $question_id);
        $wrong_answers = array();
        $correct_answer = "";
        foreach ($answer_data as $a){
            if ($a['correct'] == 0){
                $wrong_answers[] = array(
                    'text' => $a['answer'], 
                    'id' => $a['id']
                );
            }
            else{
                $correct_answer = $a['answer'];
            }
        }
        
        if (!$question_data || !$answer_data){
            $data = [
                'username' => $_SESSION['user_email'],
                'function' => 'list',
                'error' => 'Σφάλμα: η ερώτηση που ζητήσατε να επεξεργαστείτε δεν βρέθηκε.'
            ];
        }
        else{
            $data = [
                'username' => $_SESSION['user_email'],
                'function' => 'edit',
                'question_data' => $question_data[0],
                'correct_answer' => $correct_answer,
                'wrong_answers' => $wrong_answers
            ];
        }
        
        return $data;
    }
    
    /**
     * Handles the request to edit an existing binary question
     *
     * @param $user_id the user id of the teacher
     * @param $question_id the id of question to edit
     */
    public function editQuestion_bin($user_id, $question_id)
    {
        $question_data = $this->administrator->getQuestions_bin($user_id, $question_id);

        if (!$question_data){
            $data = [
                'username' => $_SESSION['user_email'],
                'function' => 'list',
                'error' => 'Σφάλμα: η ερώτηση που ζητήσατε να επεξεργαστείτε δεν βρέθηκε.'
            ];
        }
        else{
            $data = [
                'username' => $_SESSION['user_email'],
                'function' => 'edit',
                'question_data' => $question_data[0]
            ];
        }
        error_log(json_encode($data) );
        return $data;
    }
    
    /**
     * Updates the data of an existing course
     *
     * @param $params the URL attributes
     */
     public function updateQuestion($params)
    {
        $user_id = $this->check_session();
        
        if ($params["slug"] == "update")
        {
            $question_type = $this->request->getParameter('question-type');
            if ($question_type == "multi")
                $error = $this->administrator->updateQuestion_mc($user_id);
            else if ($question_type == "binary")
                $error = $this->administrator->updateQuestion_bin($user_id);
        }
        else if ($params["slug"] == "delete"){
            $error = $this->administrator->deleteCourse($user_id);
        }
        
        if ($error){
            $data = [
                'error' => true,
                'message' => $error
            ];
        }
        else{            
            $data = [
                'error' => false,
                'message' => 'Η άσκηση ενημερώθηκε επιτυχώς.'
            ];
            
            $json_reply = json_encode($data);
            $this->response->setContent($json_reply);
        }
    }
    
    /**
     * Shows the grades for a particular course
     *
     * @param $params the URL attributes
     */
    public function showGrades($params)
    {
        $user_id = $this->check_session();
        $course_id = $params['id'];
        
        $users = $this->administrator->getCourseGrades($user_id, $course_id);
        $data = [];
        if ($users){
            $data['users'] = $users;
        }
        
        //$json_reply = json_encode($data);
        $html = $this->renderer->render('Grade', $data);
        $this->response->setContent($html);
    }
}