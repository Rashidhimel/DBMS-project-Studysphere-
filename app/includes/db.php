<?php
declare(strict_types=1);

class Database {
    private $host = "localhost";
    private $db_name = "studysphere";
    private $username = "root";
    private $password = "";
    private static $conn = null;

    public function getConnection() {
        if (self::$conn === null) {
            try {
                self::$conn = new PDO(
                    "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                    $this->username,
                    $this->password
                );
                self::$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$conn->exec("set names utf8");
            } catch(PDOException $exception) {
                echo "Connection error: " . $exception->getMessage();
            }
        }
        return self::$conn;
    }
    public function query(string $sql, $params = []): PDOStatement {
        $db = $this->getConnection();
                if ($params === null) {
            $params = [];
        }
                if (!is_array($params)) {
            $params = [$params];
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    public function fetchOne(string $sql, $params = []): ?array {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result !== false ? $result : null;
    }
    public function fetchAll(string $sql, $params = []): array {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result !== false ? $result : [];
    }
    public function insert(string $table, array $data): bool {
        $columns = implode(", ", array_keys($data));
        $placeholders = ":" . implode(", :", array_keys($data));
        
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        $stmt = $this->getConnection()->prepare($sql);
        
        foreach ($data as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        
        return $stmt->execute();
    }
    public function update(string $table, array $data, string $where): bool {
        $set = "";
        foreach ($data as $key => $value) {
            $set .= "$key = :$key, ";
        }
        $set = rtrim($set, ", ");
        
        $sql = "UPDATE $table SET $set WHERE $where";
        $stmt = $this->getConnection()->prepare($sql);
        
        foreach ($data as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        
        return $stmt->execute();
    }

    public function hardDeleteUser(int $user_id): bool {
        $conn = $this->getConnection();
        
        try {            $conn->beginTransaction();
                        $student = $this->fetchOne("SELECT student_id FROM students WHERE user_id = ?", [$user_id]);
            $student_id = $student ? (int)$student['student_id'] : null;
            
            if ($student_id) {                                $this->query("DELETE FROM student_preferred_countries WHERE student_id = ?", [$student_id]);
                                $this->query("DELETE FROM part_time_preferences WHERE student_id = ?", [$student_id]);
                                $this->query("DELETE FROM student_todos WHERE student_id = ?", [$student_id]);
                                $this->query("DELETE FROM student_milestones WHERE student_id = ?", [$student_id]);
                                $this->query("DELETE FROM student_scholarships WHERE student_id = ?", [$student_id]);
                                $assignments = $this->fetchAll("SELECT assignment_id FROM counselor_assignments WHERE student_id = ?", [$student_id]);
                foreach ($assignments as $assignment) {
                    $this->query("DELETE FROM counseling_sessions WHERE assignment_id = ?", [(int)$assignment['assignment_id']]);
                }
                $this->query("DELETE FROM counselor_assignments WHERE student_id = ?", [$student_id]);
                                $applications = $this->fetchAll("SELECT application_id FROM applications WHERE student_id = ?", [$student_id]);
                foreach ($applications as $app) {                    $this->query("DELETE FROM application_attachments WHERE application_id = ?", [(int)$app['application_id']]);
                    $this->query("DELETE FROM application_reviews WHERE application_id = ?", [(int)$app['application_id']]);
                    $this->query("DELETE FROM application_events WHERE application_id = ?", [(int)$app['application_id']]);
                }                $this->query("DELETE FROM applications WHERE student_id = ?", [$student_id]);
                                $recommenders = $this->fetchAll("SELECT recommender_id FROM recommenders WHERE student_id = ?", [$student_id]);
                foreach ($recommenders as $rec) {
                    $this->query("DELETE FROM recommendations WHERE recommender_id = ?", [(int)$rec['recommender_id']]);
                }
                $this->query("DELETE FROM recommenders WHERE student_id = ?", [$student_id]);
                                $education_records = $this->fetchAll("SELECT record_id FROM education_records WHERE student_id = ?", [$student_id]);
                foreach ($education_records as $edu) {
                    $this->query("DELETE FROM education_documents WHERE education_record_id = ?", [(int)$edu['record_id']]);
                }
                $this->query("DELETE FROM education_records WHERE student_id = ?", [$student_id]);
                                $test_scores = $this->fetchAll("SELECT score_id FROM test_scores WHERE student_id = ?", [$student_id]);
                foreach ($test_scores as $test) {
                    $this->query("DELETE FROM test_score_sections WHERE score_id = ?", [(int)$test['score_id']]);
                    $this->query("DELETE FROM test_score_documents WHERE score_id = ?", [(int)$test['score_id']]);
                }
                $this->query("DELETE FROM test_scores WHERE student_id = ?", [$student_id]);
                                $achievements = $this->fetchAll("SELECT achievement_id FROM achievements WHERE student_id = ?", [$student_id]);
                foreach ($achievements as $ach) {
                    $this->query("DELETE FROM achievement_documents WHERE achievement_id = ?", [(int)$ach['achievement_id']]);
                }
                $this->query("DELETE FROM achievements WHERE student_id = ?", [$student_id]);
                                $visas = $this->fetchAll("SELECT visa_id FROM student_visas WHERE student_id = ?", [$student_id]);
                foreach ($visas as $visa) {
                    $this->query("DELETE FROM visa_documents WHERE visa_id = ?", [(int)$visa['visa_id']]);
                    $this->query("DELETE FROM visa_status_history WHERE visa_id = ?", [(int)$visa['visa_id']]);
                    $this->query("DELETE FROM visa_fee_payments WHERE visa_id = ?", [(int)$visa['visa_id']]);
                }
                $this->query("DELETE FROM student_visas WHERE student_id = ?", [$student_id]);
                                $this->query("DELETE FROM students WHERE student_id = ?", [$student_id]);
            }
                        $attachments = $this->fetchAll("SELECT attachment_id, file_url FROM attachments WHERE uploader_user_id = ?", [$user_id]);
            foreach ($attachments as $attachment) {                $this->query("DELETE FROM education_documents WHERE attachment_id = ?", [(int)$attachment['attachment_id']]);
                $this->query("DELETE FROM test_score_documents WHERE attachment_id = ?", [(int)$attachment['attachment_id']]);
                $this->query("DELETE FROM achievement_documents WHERE attachment_id = ?", [(int)$attachment['attachment_id']]);
                $this->query("DELETE FROM application_attachments WHERE attachment_id = ?", [(int)$attachment['attachment_id']]);
                $this->query("DELETE FROM visa_documents WHERE attachment_id = ?", [(int)$attachment['attachment_id']]);
                $this->query("DELETE FROM resource_files WHERE attachment_id = ?", [(int)$attachment['attachment_id']]);
                                $file_path = $_SERVER['DOCUMENT_ROOT'] . $attachment['file_url'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
                                $this->query("DELETE FROM attachments WHERE attachment_id = ?", [(int)$attachment['attachment_id']]);
            }
                        $this->query("DELETE FROM user_profiles WHERE user_id = ?", [$user_id]);
            $this->query("DELETE FROM addresses WHERE user_id = ?", [$user_id]);
            $this->query("DELETE FROM phones WHERE user_id = ?", [$user_id]);
            $this->query("DELETE FROM user_roles WHERE user_id = ?", [$user_id]);
            $this->query("DELETE FROM email_verifications WHERE user_id = ?", [$user_id]);
            $this->query("DELETE FROM password_resets WHERE user_id = ?", [$user_id]);
            $this->query("DELETE FROM login_attempts WHERE user_id = ?", [$user_id]);
            $this->query("DELETE FROM sessions WHERE user_id = ?", [$user_id]);
            $this->query("DELETE FROM notifications WHERE user_id = ?", [$user_id]);
                        $accounts = $this->fetchAll("SELECT account_id FROM finance_accounts WHERE user_id = ?", [$user_id]);
            foreach ($accounts as $account) {
                $this->query("DELETE FROM finance_transactions WHERE account_id = ?", [(int)$account['account_id']]);
            }
            $this->query("DELETE FROM finance_accounts WHERE user_id = ?", [$user_id]);
            $this->query("DELETE FROM budgets WHERE user_id = ?", [$user_id]);
                        $this->query("DELETE FROM audit_logs WHERE user_id = ?", [$user_id]);
            $this->query("DELETE FROM data_consents WHERE user_id = ?", [$user_id]);
            $this->query("DELETE FROM data_deletion_requests WHERE user_id = ?", [$user_id]);
                        $result = $this->query("DELETE FROM users WHERE user_id = ?", [$user_id]);
                        $conn->commit();
            
            return $result->rowCount() > 0;
            
        } catch (Exception $e) {            $conn->rollBack();
            error_log("Error deleting user $user_id: " . $e->getMessage());
            return false;
        }
    }
}
$database = new Database();
$db = $database->getConnection();
?>