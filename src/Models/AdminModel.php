<?php

namespace VClass\Models;

use Http\Request;
use Http\Response;
use Mailgun\Mailgun;
use VClass\Config;

class AdminModel{
    
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
     * Creates a new lesson and adds it in the database
     *
     * @param $user_id int the user ID of the teacher that requested the course creation
     * @return bool success state
     */
    public function createNewCourse($user_id)
    {
        $course_title = $this->request->getParameter('course-title');
        $course_text = $this->request->getParameter('course-text');
        
        if (empty($course_title) OR empty($course_text)) {
            $this->error = "Σφάλμα AM1: Συμπληρώστε τα όλα τα απαιτούμενα πεδία και προσπαθήστε ξανά.";
        }
                
        $sql = "INSERT INTO lessons (title, text, teacher, lesson_creation_timestamp, lesson_update_timestamp)
                VALUES (:title, :text, :teacher, :lesson_creation_timestamp, :lesson_update_timestamp)";
        
        $query = $this->pdo->prepare($sql);
        $query->execute(array(
            ':title' => $course_title,
            ':text' => $course_text,
            ':teacher' => $user_id,
            ':lesson_creation_timestamp' => time(),
            ':lesson_update_timestamp' => time()
        ));
        
        $count =  $query->rowCount();
        if ($count == 0) {
            $this->error = "Σφάλμα AM2: Η αποθήκευση του μαθήματος απέτυχε";
        }
        
        return $this->error;  
    }
    
    /**
     * Returns the existing courses created by the specific teacher
     *
     * @param $user_id the user id of the teacher
     * @return array the list of courses
     */
    public function getCourses($user_id){
        $sql = "SELECT id, title, lesson_creation_timestamp, lesson_update_timestamp 
                FROM lessons WHERE teacher = :teacher
                ORDER BY title ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array(':teacher' => $user_id));
        
        return $stmt->fetchAll();
    }
    
    /**
     * Returns the data of an existing course that belongs to the specific teacher
     *
     * @param $user_id the user id of the teacher
     * @param $course_id the id of the course to editCourse
     * @return array|bool the course data or the success status
     */
    public function getCourse($user_id, $course_id)
    {
        $course = false;
        // get the details of the requested course
        $sql = "SELECT id, title, text, lesson_creation_timestamp, lesson_update_timestamp 
                FROM lessons 
                WHERE id = :id AND teacher = :teacher
                LIMIT 1";
                
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array(
            ':id' => $course_id,
            ':teacher' => $user_id,
        ));
        
        if ($stmt->rowCount() == 1) 
        {
            $course = $stmt->fetch();
        }
        
        return $course;
    }
    
    /**
     * Updates the data of an existing course that belongs to the specific teacher
     *
     * @param $user_id the user id of the teacher
     */
    public function updateCourse($user_id)
    {
        $course_title = $this->request->getParameter('course-title');
        $course_text = $this->request->getParameter('course-text');
        $course_id = $this->request->getParameter('course-id');
        
        if (empty($course_title) OR empty($course_text) OR empty($course_id)) {
            $this->error = "Σφάλμα AM3: Συμπληρώστε τα όλα τα απαιτούμενα πεδία και προσπαθήστε ξανά.";
        }
        
        // get the details of the course and check that the user has permission to change it
        $course_data = $this->getCourse($user_id, $course_id);
        if (!$course_data)
        {
            $this->error = "Σφάλμα AM4: Το μάθημα που ζητήσατε δεν βρέθηκε.";
        }
        else
        {
            $sql = "UPDATE lessons
                    SET title = :title, text = :text, lesson_update_timestamp = :lesson_update_timestamp 
                    WHERE teacher = :teacher AND id = :id";
            
            $query = $this->pdo->prepare($sql);
            
            $query->execute(array(
                ':title' => $course_title,
                ':text' => $course_text,
                ':lesson_update_timestamp' => time(),
                ':teacher' => $user_id,
                ':id' => $course_id
            ));
            
            if ($query->rowCount() == 0) 
            {
                $this->error = "Σφάλμα AM5: Η επεξεργασία του μαθήματος απέτυχε.";
            }
        }
        
        return $this->error;        
    }
    
    /**
     * Deletes an existing course that belongs to the specific teacher
     *
     * @param $user_id the user id of the teacher
     */
    public function deleteCourse($user_id)
    {
        $course_id = $this->request->getParameter('course-id');
        if (empty($course_id)) {
            $this->error = "Σφάλμα AM6: Δεν έχετε προσδιορίσει μάθημα.";
        }
        
        // get the details of the course and check that the user has permission to change it
        $course_data = $this->getCourse($user_id, $course_id);
        if (!$course_data)
        {
            $this->error = "Σφάλμα AM7: Το μάθημα που ζητήσατε δεν βρέθηκε.";
        }
        else
        {
            $sql = "DELETE FROM lessons
                    WHERE teacher = :teacher AND id = :id";
            
            $query = $this->pdo->prepare($sql);
            
            $query->execute(array(
                ':teacher' => $user_id,
                ':id' => $course_id
            ));
            
            if ($query->rowCount() == 0) 
            {
                $this->error = "Σφάλμα AM8: Το μάθημα που ζητήσατε δεν βρέθηκε.";
            }
        }
        
        return $this->error;  
    }
    
    /**
     * Creates a new question and adds it in the database
     *
     * @param $user_id int the user ID of the teacher that requested the course creation
     * @return bool success state
     */
    public function createNewQuestion($user_id)
    {
        $instructions = $this->request->getParameter('question-instructions');
        $question = $this->request->getParameter('question-title');
        $answers = array();
        $answers[] = $this->request->getParameter('correct-answer');
        $wrong_answers = $this->request->getParameter('wrong-answer');
        $course_id = $this->request->getParameter('course_id');
        
        foreach($wrong_answers as $wa){
            $answers[] = $wa;
        }
                
        if (empty($instructions) OR empty($question) OR empty($course_id) OR sizeof($answers) == 0) {
            $this->error = "Σφάλμα AM9: Συμπληρώστε τα όλα τα απαιτούμενα πεδία και προσπαθήστε ξανά.";
        }
                
        $sql = "INSERT INTO questions_mc (title, instructions, teacher, question_creation_timestamp, question_updated_timestamp)
                VALUES (:title, :instructions, :teacher, :question_creation_timestamp, :question_updated_timestamp)";
        
        $query = $this->pdo->prepare($sql);
        $query->execute(array(
            ':title' => $question,
            ':instructions' => $instructions,
            ':teacher' => $user_id,
            ':question_creation_timestamp' => time(),
            ':question_updated_timestamp' => time()
        ));
        
        $count =  $query->rowCount();
        if ($count == 0) {
            $this->error = "Σφάλμα AM10: Η αποθήκευση της ερώτησης απέτυχε";
        }
        // if no error happened insert the answers in the DB
        else{
            // Store the ID of the question
            $question_id = $this->pdo->lastInsertId();
            $answer_ids = array(); //array that will hold the IDs of the answers
            
            $sql = "INSERT INTO answers_mc (answer, correct)
                    VALUES (:answer, :correct)";
            $query = $this->pdo->prepare($sql);
            foreach ($answers as $k=>$v)
            {
                $correct = 0;
                if ($k == 1){
                    $correct = 1;
                }
                
                $query->execute(array(
                    ':answer' => $v,
                    ':correct' => $correct
                ));
                
                $count =  $query->rowCount();
                if ($count == 0) {
                    $this->error = "Σφάλμα AM11: Η αποθήκευση της απάντησης απέτυχε";
                }
                
                $answer_ids[] = $this->pdo->lastInsertId();
            }
            // if no error happened so far, insert the mapping between answer IDs and the question's ID
            if (!$this->error){
                $sql = "INSERT INTO questions_answers_mc (question_id, answer_id)
                        VALUES (:question_id, :answer_id)";
                $query = $this->pdo->prepare($sql);
                foreach ($answer_ids as $ai)
                {
                    $query->execute(array(
                        ':question_id' => $question_id,
                        ':answer_id' => $ai
                    ));
                    
                    $count =  $query->rowCount();
                    if ($count == 0) {
                        $this->error = "Σφάλμα AM12: Η αποθήκευση της ερώτησης απέτυχε";
                    }
                }
            }
            
            // if no error happened so far, insert the mapping between course ID and the question's ID
            if (!$this->error)
            {
                $sql = "INSERT INTO questions_courses_mc (course_id, question_id)
                        VALUES (:course_id, :question_id)";
                $query = $this->pdo->prepare($sql);
                $query->execute(array(
                    ':course_id' => $course_id,
                    ':question_id' => $question_id
                ));
                
                $count =  $query->rowCount();
                if ($count == 0) {
                    $this->error = "Σφάλμα AM13: Η αποθήκευση της ερώτησης απέτυχε";
                }
            }
        }
        
        return $this->error; 
    }
    
    /**
     * Returns the existing courses created by the specific teacher
     *
     * @param $user_id the user id of the teacher
     * @param $question_id (optional) the id of the requested question
     * @return array the list of courses
     */
    public function getQuestions_mc($user_id, $question_id = false)
    {
        // get the details of the requested question
        $sql = "SELECT qmc.id, qmc.title as q_title, instructions, question_updated_timestamp, 'mc' AS qtype, l.title as lesson, l.id as lesson_id
                FROM questions_mc qmc
                INNER JOIN questions_courses_mc qcmc ON qmc.id = qcmc.question_id
                INNER JOIN lessons l ON qcmc.course_id = l.id
                WHERE qmc.teacher = :teacher";
        
        $prepare_array = array(
            ':teacher' => $user_id
        );
        
        if ($question_id != false)
        {
            $sql .= " AND qmc.id = :id";
            $prepare_array[':id'] = $question_id;            
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($prepare_array);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Returns the answers for the requested question
     *
     * @param $user_id the user id of the teacher
     * @param $question_id the id of the requested question
     * @return array the list of answers linked to the question
     */
    public function getAnswers($user_id, $question_id)
    {
        // get the answers of the requested question
        $sql = "SELECT amc.id, amc.answer, correct, q.id AS question_id
                FROM answers_mc amc
                INNER JOIN questions_answers_mc qamc ON amc.id = qamc.answer_id
                INNER JOIN questions_mc q ON qamc.question_id = q.id
                WHERE q.teacher = :teacher AND question_id = :question_id";
        
        $prepare_array = array(
            ':teacher' => $user_id,
            ':question_id' => $question_id
        );
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($prepare_array);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Updates the question and the related answers of the question
     *
     * @param $user_id the user id of the teacher
     */
    public function updateQuestion_mc($user_id)
    {
        $instructions = $this->request->getParameter('question-instructions');
        $question = $this->request->getParameter('question-title');
        $answers = array();
        $answers[] = $this->request->getParameter('correct-answer');
        error_log(json_encode($answers));
        $wrong_answers = $this->request->getParameter('wrong-answer');
        $course_id = $this->request->getParameter('course_id');
        $question_id = $this->request->getParameter('question_id');
        
        foreach($wrong_answers as $wa){
            $answers[] = $wa;
        }
        
        if (empty($instructions) OR empty($question) OR empty($course_id) OR sizeof($answers) == 0 OR empty($question_id)) {
            $this->error = "Σφάλμα AM9: Συμπληρώστε τα όλα τα απαιτούμενα πεδία και προσπαθήστε ξανά.";
        }
        
        $sql = "UPDATE questions_mc
                SET title = :title, instructions = :instructions, question_updated_timestamp = :question_updated_timestamp 
                WHERE teacher = :teacher AND id = :id";
        
        $query = $this->pdo->prepare($sql);
        
        $query->execute(array(
            ':title' => $question,
            ':instructions' => $instructions,
            ':teacher' => $user_id,
            ':question_updated_timestamp' => time(),
            ':id' => $question_id
        ));
         
        // DELETE the old answers and course related to the question
        $answers_to_delete = $this->getAnswers($user_id, $question_id);
        
        $sql = "DELETE FROM answers_mc WHERE id = :id";
        $query = $this->pdo->prepare($sql);
        foreach ($answers_to_delete as $atd){
            $query->execute(array(
                ':id' => $atd['id']
            ));
        }
        
        
        
        $sql = "DELETE FROM questions_answers_mc WHERE answer_id = :answer_id";
        $query = $this->pdo->prepare($sql);
        foreach ($answers_to_delete as $atd){
            $query->execute(array(
                ':answer_id' => $atd['id']
            ));
            
        }
        
        $sql = "DELETE FROM questions_courses_mc WHERE question_id = :question_id";
        $query = $this->pdo->prepare($sql);
        $query->execute(array(
            ':question_id' => $question_id
        ));
        error_log( json_encode($query->errorInfo()) );
        // RE-INSERT the answers and the course related to the question
        $answer_ids = array(); //array that will hold the IDs of the answers
            
        $sql = "INSERT INTO answers_mc (answer, correct)
                VALUES (:answer, :correct)";
        $query = $this->pdo->prepare($sql);
        foreach ($answers as $k=>$v)
        {
            $correct = 0;
            if ($k == 0){
                $correct = 1;
            }
            
            $query->execute(array(
                ':answer' => $v,
                ':correct' => $correct
            ));
            
            $count =  $query->rowCount();
            if ($count == 0) {
                $this->error = "Σφάλμα AM11: Η αποθήκευση της απάντησης απέτυχε";
            }
            
            $answer_ids[] = $this->pdo->lastInsertId();
        }
        // if no error happened so far, insert the mapping between answer IDs and the question's ID
        if (!$this->error){
            $sql = "INSERT INTO questions_answers_mc (question_id, answer_id)
                    VALUES (:question_id, :answer_id)";
            $query = $this->pdo->prepare($sql);
            foreach ($answer_ids as $ai)
            {
                $query->execute(array(
                    ':question_id' => $question_id,
                    ':answer_id' => $ai
                ));
                
                $count =  $query->rowCount();
                if ($count == 0) {
                    $this->error = "Σφάλμα AM12: Η αποθήκευση της ερώτησης απέτυχε";
                }
            }
        }
        
        // if no error happened so far, insert the mapping between the course ID and the question's ID
        if (!$this->error)
        {
            $sql = "INSERT INTO questions_courses_mc (course_id, question_id)
                    VALUES (:course_id, :question_id)";
            $query = $this->pdo->prepare($sql);
            $query->execute(array(
                ':course_id' => $course_id,
                ':question_id' => $question_id
            ));
            
            $count =  $query->rowCount();
            if ($count == 0) {
                $this->error = "Σφάλμα AM13: Η αποθήκευση της ερώτησης απέτυχε";
            }
            
        }
        
        return $this->error;  
    }
   
    /**
     * Creates a new binary question and adds it in the database
     *
     * @param $user_id int the user ID of the teacher that requested the course creation
     * @return bool success state
     */
    public function createNewBinaryQuestion($user_id)
    {
        $instructions = $this->request->getParameter('question-instructions');
        $question = $this->request->getParameter('question-title');
        $correctness = $this->request->getParameter('correctness');
        $lesson_id = $this->request->getParameter('course_id');
        
        if (empty($instructions) OR empty($question) OR empty($lesson_id)) {
            $this->error = "Σφάλμα AM13: Συμπληρώστε τα όλα τα απαιτούμενα πεδία και προσπαθήστε ξανά.";
        }
        
        $sql = "INSERT INTO questions_binary 
                (title, instructions, teacher, question_creation_timestamp, question_updated_timestamp, correct, lesson_id)
                VALUES (:title, :instructions, :teacher, :question_creation_timestamp, :question_updated_timestamp, :correct, :lesson_id)";
        $query = $this->pdo->prepare($sql);
        $query->execute(array(
            ':title' => $question,
            ':instructions' => $instructions,
            ':teacher' => $user_id,
            ':lesson_id' => $lesson_id,
            ':correct' => $correctness,
            ':question_creation_timestamp' => time(),
            ':question_updated_timestamp' => time()
        ));
        
        return $this->error;
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
        
        return $this->error;  
    }
    
    public function getCourseGrades($teacher_id, $course_id)
    {
        // get user namespace
        $sql="SELECT id, firstname, lastname, username FROM users WHERE role = 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $users = $stmt->fetchAll();
        
        $user_details = array();
        foreach($users as $u)
        {
            $user_details[$u['id']]['firstname'] = $u['firstname'];
            $user_details[$u['id']]['lastname'] = $u['lastname'];
            $user_details[$u['id']]['username'] = $u['username'];
        }
        
        $sql = "SELECT student_id, score_binary, score_multi, tries, update_timestamp, l.teacher  
                FROM student_completed sc
                INNER JOIN lessons l ON l.id = sc.course_id 
                WHERE course_id = :course_id AND l.teacher = :teacher
                ORDER BY update_timestamp ASC";
                
        $query = $this->pdo->prepare($sql);
        
        $query->execute(array(
            ':course_id' => $course_id,
            ':teacher' => $teacher_id
        ));
        
        $lessons_results = $query->fetchAll();
        
        error_log(json_encode($lessons_results) );
        $user_scores = array();
        foreach ($lessons_results as $result)
        {
            if (!isset($user_scores[$result['student_id']])){
                $user_scores[$result['student_id']] = array();
            }
            
            if ($result['score_multi'] != null){
                $user_scores[$result['student_id']]['score_multi'] = $result['score_multi'];
            }
            
            if ($result['score_binary'] != null){
                $user_scores[$result['student_id']]['score_binary'] = $result['score_binary'];
            }
            
            $user_scores[$result['student_id']]['last_update'] = $result['update_timestamp'];
            $user_scores[$result['student_id']]['firstname'] = $user_details[$result['student_id']]['firstname'];
            $user_scores[$result['student_id']]['lastname'] = $user_details[$result['student_id']]['lastname'];
            $user_scores[$result['student_id']]['username'] = $user_details[$result['student_id']]['username'];
        }
        
        return $user_scores;
    }
}