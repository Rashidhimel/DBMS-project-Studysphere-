<?php

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    

    public function register($username, $email, $password, $role = 'student') {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        

        $conn = $this->db->getConnection();
        $conn->beginTransaction();
        
        try {
    
            $role_id = $this->db->fetchOne(
                "SELECT role_id FROM roles WHERE name = ?",
                [$role]
            )['role_id'];
            

            $this->db->insert('users', [
                'username' => $username,
                'email' => $email,
                'password_hash' => $hashed_password,
                'default_role_id' => $role_id,
                'is_verified' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $user_id = $conn->lastInsertId();
            

            $this->db->insert('user_roles', [
                'user_id' => $user_id,
                'role_id' => $role_id
            ]);
            
  
            if ($role == 'student') {
                $this->db->insert('students', [
                    'user_id' => $user_id,
                    'degree_level_id' => 1, 
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            $conn->commit();
            return $user_id;
            
        } catch (Exception $e) {
            $conn->rollBack();
            return false;
        }
    }
    

    public function login($email, $password) {
        $user = $this->db->fetchOne(
            "SELECT u.*, r.name as role_name 
 FROM users u 
 JOIN roles r ON u.default_role_id = r.role_id 
 WHERE u.email = ? AND u.is_disabled = 0 AND u.deleted_at IS NULL",
            [$email]
        );
        
        if ($user && password_verify($password, $user['password_hash'])) {
         
            $this->db->update('users', 
                ['last_login_at' => date('Y-m-d H:i:s')],
                "user_id = " . $user['user_id']
            );
            
            
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role_name'];
            $_SESSION['is_verified'] = $user['is_verified'];
            
            return true;
        }
        
        return false;
    }
    

    public static function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    

    public function getCurrentUser() {
        if (!self::isLoggedIn()) return null;
        
        return $this->db->fetchOne(
            "SELECT u.*, r.name as role_name, up.first_name, up.last_name
             FROM users u 
             JOIN roles r ON u.default_role_id = r.role_id
             LEFT JOIN user_profiles up ON u.user_id = up.user_id
             WHERE u.user_id = ?",
            [$_SESSION['user_id']]
        );
    }
    

public function getStudentId() {
    $user = $this->getCurrentUser();
    if (!$user || $user['role_name'] != 'student') return null;
    
    $student = $this->db->fetchOne(
        "SELECT student_id FROM students WHERE user_id = ?",
        [$user['user_id']]
    );
    

    if (!$student) {
        $this->db->insert('students', [
            'user_id' => $user['user_id'],
            'degree_level_id' => 1, 
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        $student_id = $this->db->getConnection()->lastInsertId();
        return $student_id;
    }
    
    return $student['student_id'];
}
    

    public static function logout() {
        session_destroy();
        header('Location: /studysphere/public/login.php');
        exit();
    }
    

    public static function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
    }
    

    public static function isStudent() {
        return isset($_SESSION['role']) && $_SESSION['role'] == 'student';
    }

    public function getStudentCounselor($student_id) {
    $db = new Database();
    return $db->fetchOne("
        SELECT u.user_id, u.username, u.email, up.first_name, up.last_name
        FROM counselor_assignments ca
        JOIN users u ON ca.counselor_id = u.user_id
        LEFT JOIN user_profiles up ON u.user_id = up.user_id
        WHERE ca.student_id = ? AND ca.is_active = 1 AND ca.ended_at IS NULL
    ", [$student_id]);
}

public function getAssignedStudents($counselor_user_id) {
    $db = new Database();
    return $db->fetchAll("
        SELECT s.student_id, u.user_id, u.username, u.email, u.is_verified, 
               up.first_name, up.last_name, up.profile_photo_url,
               ca.assigned_at, COUNT(a.application_id) as application_count
        FROM counselor_assignments ca
        JOIN students s ON ca.student_id = s.student_id
        JOIN users u ON s.user_id = u.user_id
        LEFT JOIN user_profiles up ON u.user_id = up.user_id
        LEFT JOIN applications a ON s.student_id = a.student_id AND a.deleted_at IS NULL
        WHERE ca.counselor_id = ? AND ca.is_active = 1
        GROUP BY s.student_id
        ORDER BY up.first_name, up.last_name
    ", [$counselor_user_id]);
}

public function getCounselorSessions($counselor_user_id) {
    $db = new Database();
    return $db->fetchAll("
        SELECT cs.*, 
               s.student_id, u.username as student_username, 
               up.first_name, up.last_name
        FROM counseling_sessions cs
        JOIN counselor_assignments ca ON cs.assignment_id = ca.assignment_id
        JOIN students s ON ca.student_id = s.student_id
        JOIN users u ON s.user_id = u.user_id
        LEFT JOIN user_profiles up ON u.user_id = up.user_id
        WHERE ca.counselor_id = ? AND cs.scheduled_at > NOW()
        ORDER BY cs.scheduled_at ASC
    ", [$counselor_user_id]);
}

}
?>