<?php

namespace VClass\Controllers;

use Http\Request;
use Http\Response;
use VClass\Templates\FrontendRenderer;
use VClass\Models\StudentModel;


class Student
{
    private $request;
    private $response;
    private $renderer;
    private $student;

    public function __construct(Request $request, Response $response, FrontendRenderer $renderer, StudentModel $student)
    {
        $this->request = $request;
        $this->response = $response;
        $this->renderer = $renderer;
        $this->student = $student;
    }

    private function check_session(){
        if (!isset($_SESSION['user_email']) || 
            !isset($_SESSION['user_id']) || 
            !isset($_SESSION['user_account_type']) ||
            $_SESSION['user_account_type'] != 1)
        {
            session_destroy();
            header("Location: /");
            exit;
        }
        
        return $_SESSION['user_id'];
    }
    
    /**
     * Shows the list of courses available to the student
     * 
     * @param $params the URL parameters
     */
    public function listCourses($params)
    {
        $user_id = $this->check_session();
        
        $data = [
            'username' => $_SESSION['user_email'],
            'courses' => $this->student->listCourses($user_id)
        ];

        $html = $this->renderer->render('Student', $data);
        $this->response->setContent($html);
    }
    
    /**
     * Shows the data of courses of the requested course
     * 
     * @param $params the URL parameters
     */
    public function showCourse($params)
    {
        $user_id = $this->check_session();
        
        $data = [
            'username' => $_SESSION['user_email'],
            'course' => $this->student->getCourse($params['id'])
        ];

        $html = $this->renderer->render('Course', $data);
        $this->response->setContent($html);
    }
    
    /**
     * Shows the list of courses available to the student
     * 
     * @param $params the URL parameters
     */
    public function showExercises($params)
    {
        $user_id = $this->check_session();
        
        if ($params['slug'] == "multi")
        {
            $question_data = $this->student->getMultiTest($params['id']);
        
            $data = [
                'username' => $_SESSION['user_email'],
                'questions' => $question_data['questions'],
                'lesson' => $question_data['lesson'],
                'lesson_id' => $params['id'],
                'instructions' => $question_data['instructions'],
                'qtype' => 'multi'
            ];
        }
        else if ($params['slug'] == "binary")
        {
            $question_data = $this->student->getBinaryTest($params['id']);
            
            $data = [
                'username' => $_SESSION['user_email'],
                'questions' => $question_data['questions'],
                'lesson' => $question_data['lesson'],
                'lesson_id' => $params['id'],
                'instructions' => $question_data['instructions'],
                'qtype' => 'binary'
            ];
        }
        
        
        $html = $this->renderer->render('CourseTest', $data);
        $this->response->setContent($html);
    }
    
    public function correctExercises($params)
    {
        $user_id = $this->check_session();
        
        $qtype =  $this->request->getParameter('qtype');
        
        if ($qtype == "multi")
        {
            $question_data = $this->student->getMultiTest($params['id']);
            $result = array();
            $total_correct = 0;
            foreach($_POST as $key=>$value)
            {
                if ($this->startsWith($key, "answer-"))
                {
                    $result[$key] = 0;
                    $tokens = explode("-", $key);
                    if ($question_data['questions'][$tokens[1]]["correct"] == $value)
                    {
                        $result[$key] = 1;
                    }
                    $total_correct += $result[$key];
                }
            }
            $total_score = $total_correct / count($result);
            $data = [
                'result' => $result,
                'total_score' => $total_score
            ];
            
            // insert new course attempt
            $tries = $this->student->queryTries($user_id, $params['id']);
            $previous_binary_score = 0;
            foreach($tries as $try)
            {
                if ($try['score_binary'] != null)
                {
                    $previous_binary_score = $try['score_binary'];
                }
            }
            $this->student->newCourseAttempt($user_id, $params['id'], $total_score, $previous_binary_score);
        }
        else if($qtype == "binary")
        {
            $question_data = $this->student->getBinaryTest($params['id']);
            $result = array();
            $total_correct = 0;
            foreach($_POST as $key=>$value)
            {
                if ($this->startsWith($key, "answer-"))
                {
                    $result[$key] = 0;
                    $tokens = explode("-", $key);
                    
                    if ($question_data['questions'][$tokens[1]]["correct"] == $value)
                    {
                        $result[$key] = 1;
                    }
                    $total_correct += $result[$key];
                }
            }
            
            $total_score = $total_correct / count($result);
            $data = [
                'result' => $result,
                'total_score' => $total_score
            ];
            
            $tries = $this->student->queryTries($user_id, $params['id']);
            $previous_multi_score = 0;
            foreach($tries as $try)
            {
                if ($try['score_multi'] != null)
                {
                    $previous_multi_score = $try['score_multi'];
                }
            }
            error_log( json_encode($tries) );
            //update the exersices record
            // insert new course attempt
            $this->student->newCourseAttempt($user_id, $params['id'], $previous_multi_score, $total_score);
        }
        
        $this->response->setContent( json_encode($data));
    }
    
    private function startsWith($haystack, $needle)
    {
         $length = strlen($needle);
         return (substr($haystack, 0, $length) === $needle);
    }
}