<?php

return [
    ['GET',  '/', ['Homepage', 'show']],
    ['GET',  '/student', ['Student', 'listCourses']],
    ['GET',  '/student/course/{id:\d+}', ['Student', 'showCourse']],
    ['GET',  '/student/course/{id:\d+}/{slug}', ['Student', 'showExercises']],
    ['POST',  '/student/course/correct/{id:\d+}', ['Student', 'correctExercises']],
    ['GET',  '/admin/grades/{id:\d+}', ['Admin', 'showGrades']],
    ['GET',  '/admin/tests[/{slug}[/{id:\d+}]]', ['Admin', 'manageTests']],
    ['GET',  '/admin[/{slug}[/{id:\d+}]]', ['Admin', 'show']],
    ['POST', '/login', ['Login', 'login' ]],
    ['GET',  '/logout', ['Login', 'logout' ]],
    ['POST', '/reset/request', ['PasswordReset', 'requestPasswordRest' ]],
    ['GET',  '/reset/new', ['PasswordReset', 'verifyPasswordReset' ]],
    ['POST', '/reset/new', ['PasswordReset', 'setNewPassword' ]],
    ['POST', '/register', ['Registration', 'register' ]],
    ['GET',  '/activate', ['Registration', 'activate' ]],
    ['GET',  '/{slug}', ['Page', 'show']],
    ['POST', '/course/new', ['Admin', 'addNewCourse' ]],
    ['POST', '/course/{slug}', ['Admin', 'updateCourse' ]],
    ['POST', '/test/new', ['Admin', 'addNewQuestion' ]],
    ['POST', '/test/{slug}', ['Admin', 'updateQuestion' ]],
];