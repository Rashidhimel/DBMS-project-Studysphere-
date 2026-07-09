<?php

class UploadHelper {
    private $db;
    private $storage_limit = 50 * 1024 * 1024; // 50MB in bytes
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Get total storage used by a user in bytes
     * @param int $user_id
     * @return int total bytes
     */
    public function getUserStorageUsed($user_id) {
        $result = $this->db->fetchOne(
            "SELECT SUM(file_size) as total FROM attachments WHERE uploader_user_id = ? AND deleted_at IS NULL",
            [$user_id]
        );
        return $result['total'] ?? 0;
    }
    
    /**
     * Check if user has enough storage space
     * @param int $user_id
     * @param int $new_file_size size of file to upload in bytes
     * @return bool true if under limit, false if would exceed
     */
    public function hasStorageSpace($user_id, $new_file_size) {
        $used = $this->getUserStorageUsed($user_id);
        return ($used + $new_file_size) <= $this->storage_limit;
    }
    
    /**
     * Get remaining storage space in bytes
     * @param int $user_id
     * @return int remaining bytes
     */
    public function getRemainingStorage($user_id) {
        $used = $this->getUserStorageUsed($user_id);
        return max(0, $this->storage_limit - $used);
    }
    
    /**
     * Upload a file with storage limit check
     */
    public function uploadFile($file, $uploader_id, $type = 'document') {
        // Check storage limit first
        if (!$this->hasStorageSpace($uploader_id, $file['size'])) {
            $remaining_mb = round($this->getRemainingStorage($uploader_id) / (1024 * 1024), 2);
            return ['error' => "Storage limit exceeded. You have 50MB total storage. Remaining space: {$remaining_mb}MB. Please delete some files before uploading."];
        }
        
        $target_dir = STORAGE_PATH . "/uploads/attachments/";
        
        // Create directory if it doesn't exist
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        // Generate unique filename
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $new_filename = uniqid() . '_' . time() . '.' . $file_ext;
        $target_file = $target_dir . $new_filename;
        
        // Validate file type
        $allowed_types = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt'];
        
        if (!in_array($file_ext, $allowed_types)) {
            return ['error' => 'File type not allowed. Only PDF, DOC, JPG, PNG files are allowed.'];
        }
        
        // Validate individual file size (5MB max per file)
        if ($file['size'] > 5 * 1024 * 1024) {
            return ['error' => 'File is too large. Maximum size per file is 5MB.'];
        }
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            // Prepare data for database
            $data = [
                'uploader_user_id' => $uploader_id,
                'filename' => $file['name'],
                'file_url' => '/storage/uploads/attachments/' . $new_filename,
                'file_type' => $file['type'],
                'file_size' => $file['size'],
                'checksum' => md5_file($target_file),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            try {
                $this->db->insert('attachments', $data);
                $attachment_id = $this->db->getConnection()->lastInsertId();
                
                if (!$attachment_id) {
                    // Clean up file if database insert failed
                    if (file_exists($target_file)) {
                        unlink($target_file);
                    }
                    return ['error' => 'Failed to get attachment ID after insertion.'];
                }
                
                return [
                    'success' => true,
                    'attachment_id' => $attachment_id,
                    'filename' => $file['name'],
                    'file_url' => '/storage/uploads/attachments/' . $new_filename
                ];
            } catch (PDOException $e) {
                // Clean up file if database insert failed
                if (file_exists($target_file)) {
                    unlink($target_file);
                }
                return ['error' => 'Database error: ' . $e->getMessage()];
            }
        } else {
            return ['error' => 'Failed to upload file.'];
        }
    }
    
    /**
     * Get user attachments with category info
     */
    public function getUserAttachments($user_id) {
        return $this->db->fetchAll(
            "SELECT a.*, 
                    a.category as selected_category,
                    GROUP_CONCAT(DISTINCT 
                        CASE 
                            WHEN ed.education_record_id IS NOT NULL THEN 'Education'
                            WHEN tsd.score_id IS NOT NULL THEN 'Test Score'
                            WHEN ad.achievement_id IS NOT NULL THEN 'Achievement'
                            WHEN aa.application_id IS NOT NULL THEN 'Application'
                            WHEN r.letter_attachment_id IS NOT NULL THEN 'Recommendation'
                            WHEN v.document_attachment_id IS NOT NULL THEN 'Visa'
                            ELSE 'Uncategorized'
                        END
                    ) as categories,
                    COUNT(DISTINCT ed.education_record_id) + 
                    COUNT(DISTINCT tsd.score_id) + 
                    COUNT(DISTINCT ad.achievement_id) +
                    COUNT(DISTINCT aa.application_id) as usage_count
             FROM attachments a
             LEFT JOIN education_documents ed ON a.attachment_id = ed.attachment_id
             LEFT JOIN test_score_documents tsd ON a.attachment_id = tsd.attachment_id
             LEFT JOIN achievement_documents ad ON a.attachment_id = ad.attachment_id
             LEFT JOIN application_attachments aa ON a.attachment_id = aa.attachment_id
             LEFT JOIN recommendations r ON a.attachment_id = r.letter_attachment_id
             LEFT JOIN student_visas v ON a.attachment_id = v.document_attachment_id
             WHERE a.uploader_user_id = ? AND a.deleted_at IS NULL
             GROUP BY a.attachment_id
             ORDER BY a.created_at DESC",
            [$user_id]
        );
    }
    
    /**
     * Get student attachments (for admin/counselor view)
     */
    public function getStudentAttachments($student_id) {
        return $this->db->fetchAll(
            "SELECT a.* FROM attachments a
             JOIN students s ON a.uploader_user_id = s.user_id
             WHERE s.student_id = ? AND a.deleted_at IS NULL
             ORDER BY a.created_at DESC",
            [$student_id]
        );
    }
    
    /**
     * Link attachment to education record
     */
    public function linkToEducation($attachment_id, $education_record_id) {
        $attachment = $this->db->fetchOne(
            "SELECT attachment_id FROM attachments WHERE attachment_id = ? AND deleted_at IS NULL",
            [$attachment_id]
        );
        
        if (!$attachment) {
            return ['error' => 'Attachment not found'];
        }
        
        $education = $this->db->fetchOne(
            "SELECT record_id FROM education_records WHERE record_id = ?",
            [$education_record_id]
        );
        
        if (!$education) {
            return ['error' => 'Education record not found'];
        }
        
        try {
            $this->db->insert('education_documents', [
                'education_record_id' => $education_record_id,
                'attachment_id' => $attachment_id,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            return ['success' => true];
        } catch (PDOException $e) {
            return ['error' => 'Failed to link document: ' . $e->getMessage()];
        }
    }
    
    /**
     * Link attachment to test score
     */
    public function linkToTestScore($attachment_id, $score_id) {
        $attachment = $this->db->fetchOne(
            "SELECT attachment_id FROM attachments WHERE attachment_id = ? AND deleted_at IS NULL",
            [$attachment_id]
        );
        
        if (!$attachment) {
            return ['error' => 'Attachment not found'];
        }
        
        try {
            $this->db->insert('test_score_documents', [
                'score_id' => $score_id,
                'attachment_id' => $attachment_id,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            return ['success' => true];
        } catch (PDOException $e) {
            return ['error' => 'Failed to link document: ' . $e->getMessage()];
        }
    }
    
    /**
     * Soft delete attachment (marks as deleted)
     */
    public function deleteAttachment($attachment_id) {
        // Get file info first to delete physical file
        $file = $this->db->fetchOne(
            "SELECT * FROM attachments WHERE attachment_id = ?",
            [$attachment_id]
        );
        
        if ($file) {
            // Delete physical file
            $filepath = str_replace('/storage/', '../storage/', $file['file_url']);
            if (file_exists($filepath)) {
                unlink($filepath);
            }
        }
        
        // Soft delete in database
        return $this->db->update('attachments', 
            ['deleted_at' => date('Y-m-d H:i:s')],
            "attachment_id = $attachment_id"
        );
    }
    
    /**
     * Get storage statistics for a user
     */
    public function getStorageStats($user_id) {
        $stats = $this->db->fetchOne("
            SELECT 
                COUNT(*) as total_files,
                SUM(file_size) as total_size,
                AVG(file_size) as avg_size,
                MAX(file_size) as largest_file,
                MIN(file_size) as smallest_file
            FROM attachments 
            WHERE uploader_user_id = ? AND deleted_at IS NULL
        ", [$user_id]);
        
        if (!$stats['total_files']) {
            $stats = [
                'total_files' => 0,
                'total_size' => 0,
                'avg_size' => 0,
                'largest_file' => 0,
                'smallest_file' => 0
            ];
        }
        
        $stats['remaining_space'] = $this->getRemainingStorage($user_id);
        $stats['used_percentage'] = min(100, round(($stats['total_size'] / $this->storage_limit) * 100, 2));
        
        return $stats;
    }
}