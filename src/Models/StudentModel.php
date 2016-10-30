<?php

namespace VClass\Models;

use Http\Request;
use Http\Response;
use Mailgun\Mailgun;
use VClass\Config;

class StudentModel{
    
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
     * Returns the existing courses created by the specific teacher
     *
     * @param $user_id the user id of the teacher
     * @return array the list of courses
     */
    public function listCourses($user_id){
        $sql = "SELECT id, title  FROM lessons 
                ORDER BY title ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        
        $courses = $stmt->fetchAll();
        
        $sql = "SELECT course_id, score_multi, score_binary, tries, update_timestamp  
                FROM student_completed 
                WHERE student_id = :student_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array(
            ":student_id" => $user_id
        ));
        
        $results = $stmt->fetchAll();
        
        $next = true;
        $course_data = array();
        foreach($courses as $course)
        {
            $course_data[$course['id']] = array();
            $course_data[$course['id']]['score'] = "-";
            $course_data[$course['id']]['tries'] = "0";
            $course_data[$course['id']]['update_timestamp'] = "-";
            if ($next)
            {
                $course_data[$course['id']]['completed'] = 0;
                $next = false;
            }
            else{
                $course_data[$course['id']]['completed'] = -1;
            }
            foreach ($results as $result){
                if ($result['course_id'] == $course['id']){
                    $course_data[$course['id']]['score_multi'] = $result['score_multi'];
                    $course_data[$course['id']]['score_binary'] = $result['score_binary'];
                    $course_data[$course['id']]['tries'] = $result['tries'];
                    $course_data[$course['id']]['update_timestamp'] = date('d-m-Y H:i:s', $result['update_timestamp']);
                    if ($course_data[$course['id']]['score_multi'] > 0.8 && $course_data[$course['id']]['score_binary'] > 0.8){                        
                        $course_data[$course['id']]['completed'] = 1;
                        $next = true;
                    }
                }
            }
            
            $course_data[$course['id']]['title'] = $course['title'];
        }
        
        return $course_data;
    }
    
    /**
     * Returns the data of an existing course
     *
     * @param $course_id the id of the course to editCourse
     * @return array|bool the course data or the success status
     */
    public function getCourse($course_id)
    {
        $course = false;
        // get the details of the requested course
        $sql = "SELECT l.id, title, text, lesson_creation_timestamp, lesson_update_timestamp, u.firstname, u.lastname
                FROM lessons l
                INNER JOIN users u ON l.teacher = u.id
                WHERE l.id = :id
                LIMIT 1";
                
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array(
            ':id' => $course_id
        ));
        
        if ($stmt->rowCount() == 1) 
        {
            $course = $stmt->fetch();
        }
        
        return $course;
    }
    
    /**
     * Returns the answers for the requested question
     *
     * @param $user_id the user id of the teacher
     * @param $question_id the id of the requested question
     * @return array the list of answers linked to the question
     */
    public function getAnswers($question_id)
    {
        // get the answers of the requested question
        $sql = "SELECT amc.id, amc.answer, correct, q.id AS question_id
                FROM answers_mc amc
                INNER JOIN questions_answers_mc qamc ON amc.id = qamc.answer_id
                INNER JOIN questions_mc q ON qamc.question_id = q.id
                WHERE question_id = :question_id
                ORDER BY RAND()";
        
        $prepare_array = array(
            ':question_id' => $question_id
        );
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($prepare_array);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Returns the existing courses created by the specific teacher
     *
     * @param $user_id the user id of the teacher
     * @param $question_id (optional) the id of the requested question
     * @return array the list of courses
     */
    public function getMultiTest($lesson_id)
    {
        // get the details of the requested question
        $sql = "SELECT qmc.id, qmc.title as q_title, instructions, question_updated_timestamp, 'mc' AS qtype, l.title as lesson, l.id as lesson_id
                FROM questions_mc qmc
                INNER JOIN questions_courses_mc qcmc ON qmc.id = qcmc.question_id
                INNER JOIN lessons l ON qcmc.course_id = l.id
                WHERE l.id = :lesson_id";
        
        $prepare_array = array(
            ':lesson_id' => $lesson_id
        );
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($prepare_array);
        
        $questions = $stmt->fetchAll();
        $question_data = array();
        $lesson = "";
        $instructions = "";
        foreach($questions as $q)
        {
            $answers = $this->getAnswers($q['id']);
            $question_data[$q["id"]]["title"] = $q["q_title"];
            $lesson = $q["lesson"];
            $instructions = $q["instructions"];
            $question_data[$q["id"]]["answers"] = array();
            foreach ($answers as $a)
            {
                $question_data[$q["id"]]["answers"][$a['id']] = $a['answer'];
                if ($a['correct'] == 1)
                {
                    $question_data[$q["id"]]["correct"] = $a['id'];
                }
            }
            
        }
        
        //error_log( json_encode($stmt->errorInfo()) );
        return array("questions"=>$question_data, "lesson"=>$lesson, "instructions"=>$instructions);
    }
    
    public function getBinaryTest($lesson_id)
    {
        // get the details of the requested question
        $sql = "SELECT qbin.id, qbin.title as q_title, correct, instructions, question_updated_timestamp, 'bin' AS qtype, l.title as lesson, l.id as lesson_id
                FROM questions_binary qbin
                INNER JOIN lessons l ON qbin.lesson_id = l.id
                WHERE l.id = :lesson_id";
                
        $prepare_array = array(
            ':lesson_id' => $lesson_id
        );
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($prepare_array);
        $questions = $stmt->fetchAll();
        $question_data = array();
        $lesson = "";
        $instructions = "";
        foreach($questions as $q)
        {
            $question_data[$q["id"]]["title"] = $q["q_title"];
            $question_data[$q["id"]]["correct"] = $q["correct"];
            $lesson = $q["lesson"];
            $instructions = $q["instructions"];
        }
        error_log( json_encode($stmt->errorInfo()) );
        return array("questions"=>$question_data, "lesson"=>$lesson, "instructions"=>$instructions);
    }
    
    public function queryTries($user_id, $lesson_id)
    {
        $sql = "SELECT score_binary, score_multi, tries, update_timestamp  
                FROM student_completed 
                WHERE student_id = :student_id AND course_id = :course_id
                ORDER BY update_timestamp ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array(
            ":student_id" => $user_id,
            ":course_id" => $lesson_id
        ));
        error_log( json_encode($stmt->errorInfo()) );
        return $stmt->fetchAll();
    }
    
    public function newCourseAttempt($user_id, $lesson_id, $score_multi, $score_binary)
    {
        $sql = "INSERT INTO student_completed (student_id, course_id, score_multi, score_binary, update_timestamp) 
               VALUES (:student_id, :course_id, :score_multi, :score_binary, :update_timestamp)";
        
        $stmt = $this->pdo->prepare($sql);
        
        $stmt->execute(array(
            ":student_id" => $user_id,
            ":course_id" => $lesson_id,
            ":score_multi" => $score_multi, 
            ":score_binary" => $score_binary, 
            ":update_timestamp" => time()
        ));
        
    }
    
    /**
     * Returns the existing binary questions created by the specific teacher
     *
     * @param $user_id the user id of the teacher
     * @param $question_id (optional) the id of the requested question
     * @return array the list of courses
     */
    public function getQuestions_bin($user_id, $question_id = false)
    {
        // get the details of the requested question
        $sql = "SELECT qbin.id, qbin.title as q_title, instructions, question_updated_timestamp, 'binary' AS qtype, l.title as lesson, l.id as lesson_id
                FROM questions_binary qbin
                INNER JOIN lessons l ON qbin.lesson_id = l.id
                WHERE qbin.teacher = :teacher";
        
        $prepare_array = array(
            ':teacher' => $user_id
        );
        
        if ($question_id != false)
        {
            $sql .= " AND qbin.id = :id";
            $prepare_array[':id'] = $question_id;            
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($prepare_array);
        //error_log( json_encode($query->errorInfo()) );
        return $stmt->fetchAll();
    }
    
     /**
     * Updates the question and the related answers of the question
     *
     * @param $user_id the user id of the teacher
     */
    public function updateQuestion_bin($user_id)
    {
        $instructions = $this->request->getParameter('question-instructions');
        $question = $this->request->getParameter('question-title');
        $correctness = $this->request->getParameter('correctness');
        $lesson_id = $this->request->getParameter('course_id');
        $question_id = $this->request->getParameter('question_id');
        
        if (empty($instructions) OR empty($question) OR empty($lesson_id) OR empty($question_id) ) {
            $this->error = "Σφάλμα AM14: Συμπληρώστε τα όλα τα απαιτούμενα πεδία και προσπαθήστε ξανά.";
        }
        
        $sql = "UPDATE questions_binary
                SET title = :title, instructions = :instructions, question_updated_timestamp = :question_updated_timestamp, correct = :correct, lesson_id = :lesson_id
                WHERE teacher = :teacher AND id = :id";
        
        $query = $this->pdo->prepare($sql);
        
        $query->execute(array(
            ':title' => $question,
            ':instructions' => $instructions,
            ':question_updated_timestamp' => time(),
            ':correct' => $correctness,
            ':lesson_id' => $lesson_id,
            ':teacher' => $user_id,
            ':id' => $question_id
        ));
        error_log( json_encode($query->errorInfo()) );
        return $this->error;  
    }
   
}