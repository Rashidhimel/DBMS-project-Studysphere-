<?php

class MatchEngine {
    private $db;
    private $weights = [
        'budget' => 0.25,
        'test_scores' => 0.20,
        'gpa' => 0.15,
        'country_preference' => 0.15,
        'degree_level' => 0.10,
        'subject_match' => 0.10,
        'deadline_urgency' => 0.05
    ];
    
    public function __construct() {
        $this->db = new Database();
    }
    
        public function getStudentProfile($student_id) {
        return $this->db->fetchOne(
            "SELECT s.*, u.user_id, up.first_name, up.last_name,
                    dl.name as degree_level_name, c.name as country_name,
                    sub.name as subject_name
             FROM students s
             JOIN users u ON s.user_id = u.user_id
             LEFT JOIN user_profiles up ON u.user_id = up.user_id
             LEFT JOIN degree_levels dl ON s.degree_level_id = dl.degree_level_id
             LEFT JOIN countries c ON s.country_from = c.iso2
             LEFT JOIN subjects sub ON s.subject_area_id = sub.subject_id
             WHERE s.student_id = ?",
            [$student_id]
        );
    }
    
    
    public function getStudentPreferredCountries($student_id) {
        $countries = $this->db->fetchAll(
            "SELECT c.* FROM countries c
             JOIN student_preferred_countries spc ON c.iso2 = spc.country_code
             WHERE spc.student_id = ?",
            [$student_id]
        );
        
        $country_codes = [];
        foreach ($countries as $country) {
            $country_codes[] = $country['iso2'];
        }
        
        return $country_codes;
    }
    
    
    public function getStudentTestScores($student_id) {
        return $this->db->fetchAll(
            "SELECT ts.*, tt.name as test_name, tt.code as test_code
             FROM test_scores ts
             JOIN test_types tt ON ts.test_type_id = tt.test_type_id
             WHERE ts.student_id = ? AND (ts.valid_until IS NULL OR ts.valid_until >= CURDATE())
             ORDER BY ts.test_date DESC",
            [$student_id]
        );
    }
    
    
    public function getStudentGPA($student_id) {
        $gpa = $this->db->fetchOne(
            "SELECT MAX(gpa) as max_gpa FROM education_records 
             WHERE student_id = ? AND gpa IS NOT NULL AND deleted_at IS NULL",
            [$student_id]
        );
        
        return $gpa ? $gpa['max_gpa'] : null;
    }
    
    
    public function calculateMatchScore($student_id, $program_id) {
        $student = $this->getStudentProfile($student_id);
        $program = $this->getProgramDetails($program_id);
        
        if (!$student || !$program) {
            return 0;
        }
        
        $scores = [];
        
        
        $scores['budget'] = $this->calculateBudgetMatch(
            $student['budget_min'], 
            $student['budget_max'], 
            $program['tuition_fee']
        );
        
        
        $test_scores = $this->getStudentTestScores($student_id);
        $program_requirements = $this->getProgramTestRequirements($program_id);
        $scores['test_scores'] = $this->calculateTestScoreMatch($test_scores, $program_requirements);
        
       
        $student_gpa = $this->getStudentGPA($student_id);
        $scores['gpa'] = $this->calculateGPAMatch($student_gpa, $program['min_gpa_requirement']);
        
        
        $preferred_countries = $this->getStudentPreferredCountries($student_id);
        $scores['country_preference'] = $this->calculateCountryMatch($preferred_countries, $program['country_code']);
        
        
        $scores['degree_level'] = $this->calculateDegreeLevelMatch($student['degree_level_id'], $program['degree_level_id']);
        
        
        $scores['subject_match'] = $this->calculateSubjectMatch($student['subject_area_id'], $program['program_id']);
        
       
        $scores['deadline_urgency'] = $this->calculateDeadlineUrgency($program['application_deadline']);
        
        
        $total_score = 0;
        foreach ($this->weights as $criterion => $weight) {
            $total_score += $scores[$criterion] * $weight;
        }
        
        return min(100, max(0, round($total_score)));
    }
    
    
    public function getProgramDetails($program_id) {
        $program = $this->db->fetchOne(
            "SELECT p.*, u.name as university_name, u.country_code,
                    dl.name as degree_level_name,
                    (SELECT MIN(minimum_value) FROM program_requirements 
                     WHERE program_id = p.program_id AND requirement_type = 'gpa') as min_gpa_requirement
             FROM programs p
             JOIN universities u ON p.university_id = u.university_id
             JOIN degree_levels dl ON p.degree_level_id = dl.degree_level_id
             WHERE p.program_id = ? AND p.deleted_at IS NULL",
            [$program_id]
        );
        
        return $program;
    }
    
    
    private function getProgramTestRequirements($program_id) {
        return $this->db->fetchAll(
            "SELECT prt.*, tt.name as test_name, tt.code as test_code
             FROM program_requirement_tests prt
             JOIN program_requirements pr ON prt.req_id = pr.req_id
             JOIN test_types tt ON prt.test_type_id = tt.test_type_id
             WHERE pr.program_id = ? AND prt.is_mandatory = 1",
            [$program_id]
        );
    }
    
    
    private function calculateBudgetMatch($budget_min, $budget_max, $tuition_fee) {
        if (!$tuition_fee) {
            return 50; 
        }
        
        if (!$budget_min && !$budget_max) {
            return 50; 
        }
        
        if ($budget_min && $tuition_fee < $budget_min) {
            return 80; 
        }
        
        if ($budget_max && $tuition_fee <= $budget_max) {
            return 100; 
        }
        
        if ($budget_max && $tuition_fee > $budget_max) {
            
            $over_percentage = (($tuition_fee - $budget_max) / $budget_max) * 100;
            if ($over_percentage <= 10) {
                return 60; 
            } elseif ($over_percentage <= 25) {
                return 40; 
            } else {
                return 20; 
            }
        }
        
        return 50;
    }
    
    
    private function calculateTestScoreMatch($student_scores, $program_requirements) {
        if (empty($program_requirements)) {
            return 100; 
        }
        
        if (empty($student_scores)) {
            return 0; 
        }
        
        $total_score = 0;
        $requirements_met = 0;
        
        foreach ($program_requirements as $requirement) {
            $student_score = null;
            
            
            foreach ($student_scores as $score) {
                if ($score['test_type_id'] == $requirement['test_type_id']) {
                    $student_score = $score;
                    break;
                }
            }
            
            if ($student_score) {
                
                $requirements_met++;
                $sections = $this->db->fetchAll(
                    "SELECT * FROM test_score_sections WHERE score_id = ?",
                    [$student_score['score_id']]
                );
                
                if (!empty($sections)) {
                    
                    $total_score += 80;
                } else {
                    
                    $total_score += 70;
                }
            } else {
                $total_score += 30; 
            }
        }
        
        return $total_score / count($program_requirements);
    }
    
    
    private function calculateGPAMatch($student_gpa, $min_gpa_requirement) {
        if (!$student_gpa) {
            return 50; 
        }
        
        if (!$min_gpa_requirement) {
            return 80; 
        }
        
        if ($student_gpa >= $min_gpa_requirement) {
            
            $above_percentage = (($student_gpa - $min_gpa_requirement) / $min_gpa_requirement) * 100;
            if ($above_percentage >= 20) {
                return 100;
            } elseif ($above_percentage >= 10) {
                return 90;
            } else {
                return 80;
            }
        } else {
           
            $below_percentage = (($min_gpa_requirement - $student_gpa) / $min_gpa_requirement) * 100;
            if ($below_percentage <= 5) {
                return 60; 
            } elseif ($below_percentage <= 10) {
                return 40; 
            } else {
                return 20;
            }
        }
    }
    
    
    private function calculateCountryMatch($preferred_countries, $program_country) {
        if (empty($preferred_countries)) {
            return 50; 
        }
        
        if (in_array($program_country, $preferred_countries)) {
            return 100; 
        }
        
        
        $program_country_data = $this->db->fetchOne(
            "SELECT region FROM countries WHERE iso2 = ?",
            [$program_country]
        );
        
        $program_region = $program_country_data ? $program_country_data['region'] : null;
        
        if ($program_region) {
            foreach ($preferred_countries as $country_code) {
                $country_data = $this->db->fetchOne(
                    "SELECT region FROM countries WHERE iso2 = ?",
                    [$country_code]
                );
                
                if ($country_data && $country_data['region'] == $program_region) {
                    return 70;
                }
            }
        }
        
        return 30; 
    }
    
    
    private function calculateDegreeLevelMatch($student_degree_level, $program_degree_level) {
        if ($student_degree_level == $program_degree_level) {
            return 100; 
        }
        
        
        $degree_levels = [
            'UG' => 1,
            'GRAD' => 2,
            'PHD' => 3
        ];
        
        if (isset($degree_levels[$student_degree_level]) && 
            isset($degree_levels[$program_degree_level])) {
            
            if ($degree_levels[$program_degree_level] > $degree_levels[$student_degree_level]) {
                return 90; 
            } else {
                return 50; 
            }
        }
        
        return 50;
    }
    
    
    private function calculateSubjectMatch($student_subject_id, $program_id) {
        if (!$student_subject_id) {
            return 50; 
        }
        
        
        $program_subject = $this->db->fetchOne(
            "SELECT * FROM program_subjects 
             WHERE program_id = ? AND subject_id = ?",
            [$program_id, $student_subject_id]
        );
        
        if ($program_subject) {
            return 100; 
        }
        
        
        $program_subjects = $this->db->fetchAll(
            "SELECT s.* FROM program_subjects ps
             JOIN subjects s ON ps.subject_id = s.subject_id
             WHERE ps.program_id = ?",
            [$program_id]
        );
        
        if (!empty($program_subjects)) {
            return 70; 
        }
        
        return 30; 
    }
    
    
    private function calculateDeadlineUrgency($deadline) {
        if (!$deadline) {
            return 50; 
        }
        
        $now = new DateTime();
        $deadline_date = new DateTime($deadline);
        $days_until_deadline = $now->diff($deadline_date)->days;
        
        if ($days_until_deadline < 0) {
            return 20; 
        } elseif ($days_until_deadline <= 30) {
            return 90; 
        } elseif ($days_until_deadline <= 60) {
            return 80; 
        } elseif ($days_until_deadline <= 120) {
            return 70; 
        } else {
            return 60; 
        }
    }
    
    
    public function getTopRecommendations($student_id, $limit = 10) {
        
        $programs = $this->db->fetchAll(
            "SELECT p.*, u.name as university_name, u.country_code,
                    dl.name as degree_level_name,
                    (SELECT MIN(minimum_value) FROM program_requirements 
                     WHERE program_id = p.program_id AND requirement_type = 'gpa') as min_gpa_requirement
             FROM programs p
             JOIN universities u ON p.university_id = u.university_id
             JOIN degree_levels dl ON p.degree_level_id = dl.degree_level_id
             WHERE p.deleted_at IS NULL
             ORDER BY p.created_at DESC"
        );
        
        $recommendations = [];
        
        foreach ($programs as $program) {
            $match_score = $this->calculateMatchScore($student_id, $program['program_id']);
            
            if ($match_score > 50) { 
                $recommendations[] = [
                    'program_id' => $program['program_id'],
                    'program_name' => $program['program_name'],
                    'university_name' => $program['university_name'],
                    'country_code' => $program['country_code'],
                    'degree_level' => $program['degree_level_name'],
                    'tuition_fee' => $program['tuition_fee'],
                    'currency' => $program['currency'],
                    'application_deadline' => $program['application_deadline'],
                    'match_score' => $match_score,
                    'match_category' => $this->getMatchCategory($match_score)
                ];
            }
        }
        
        
        usort($recommendations, function($a, $b) {
            return $b['match_score'] <=> $a['match_score'];
        });
        
        
        return array_slice($recommendations, 0, $limit);
    }
    
    
    private function getMatchCategory($score) {
        if ($score >= 90) return 'Perfect Match';
        if ($score >= 80) return 'Excellent Match';
        if ($score >= 70) return 'Good Match';
        if ($score >= 60) return 'Fair Match';
        return 'Low Match';
    }
    
   
    public function getMatchBreakdown($student_id, $program_id) {
        $student = $this->getStudentProfile($student_id);
        $program = $this->getProgramDetails($program_id);
        
        $breakdown = [];
        
       
        $breakdown['budget'] = [
            'score' => $this->calculateBudgetMatch(
                $student['budget_min'], 
                $student['budget_max'], 
                $program['tuition_fee']
            ),
            'weight' => $this->weights['budget'],
            'description' => $this->getBudgetDescription($student, $program)
        ];
        
        
        $test_scores = $this->getStudentTestScores($student_id);
        $program_requirements = $this->getProgramTestRequirements($program_id);
        $breakdown['test_scores'] = [
            'score' => $this->calculateTestScoreMatch($test_scores, $program_requirements),
            'weight' => $this->weights['test_scores'],
            'description' => $this->getTestScoreDescription($test_scores, $program_requirements)
        ];
        
        
        $student_gpa = $this->getStudentGPA($student_id);
        $breakdown['gpa'] = [
            'score' => $this->calculateGPAMatch($student_gpa, $program['min_gpa_requirement']),
            'weight' => $this->weights['gpa'],
            'description' => $this->getGPADescription($student_gpa, $program['min_gpa_requirement'])
        ];
        
       
        $preferred_countries = $this->getStudentPreferredCountries($student_id);
        $breakdown['country_preference'] = [
            'score' => $this->calculateCountryMatch($preferred_countries, $program['country_code']),
            'weight' => $this->weights['country_preference'],
            'description' => $this->getCountryDescription($preferred_countries, $program['country_code'])
        ];
        
        
        $breakdown['degree_level'] = [
            'score' => $this->calculateDegreeLevelMatch($student['degree_level_id'], $program['degree_level_id']),
            'weight' => $this->weights['degree_level'],
            'description' => $this->getDegreeLevelDescription($student['degree_level_id'], $program['degree_level_id'])
        ];
        
        
        $breakdown['subject_match'] = [
            'score' => $this->calculateSubjectMatch($student['subject_area_id'], $program['program_id']),
            'weight' => $this->weights['subject_match'],
            'description' => $this->getSubjectDescription($student['subject_area_id'], $program['program_id'])
        ];
        
        
        $breakdown['deadline_urgency'] = [
            'score' => $this->calculateDeadlineUrgency($program['application_deadline']),
            'weight' => $this->weights['deadline_urgency'],
            'description' => $this->getDeadlineDescription($program['application_deadline'])
        ];
        
        return $breakdown;
    }
    
    
    private function getBudgetDescription($student, $program) {
        if (!$program['tuition_fee']) {
            return "Tuition fee information not available";
        }
        
        if (!$student['budget_min'] && !$student['budget_max']) {
            return "No budget preference set";
        }
        
        if ($student['budget_max'] && $program['tuition_fee'] <= $student['budget_max']) {
            return "Within your budget range";
        }
        
        return "Above your maximum budget";
    }
    
    private function getTestScoreDescription($test_scores, $requirements) {
        if (empty($requirements)) {
            return "No test requirements";
        }
        
        if (empty($test_scores)) {
            return "No test scores submitted";
        }
        
        $met = 0;
        foreach ($requirements as $req) {
            foreach ($test_scores as $score) {
                if ($score['test_type_id'] == $req['test_type_id']) {
                    $met++;
                    break;
                }
            }
        }
        
        return "$met out of " . count($requirements) . " test requirements met";
    }
    
    private function getGPADescription($student_gpa, $required_gpa) {
        if (!$student_gpa) {
            return "No GPA recorded";
        }
        
        if (!$required_gpa) {
            return "No minimum GPA requirement";
        }
        
        if ($student_gpa >= $required_gpa) {
            return "Meets GPA requirement";
        }
        
        return "Below minimum GPA requirement";
    }
    
    private function getCountryDescription($preferred_countries, $program_country) {
        if (empty($preferred_countries)) {
            return "No country preference set";
        }
        
        if (in_array($program_country, $preferred_countries)) {
            return "Matches your preferred country";
        }
        
        return "Not in your preferred countries";
    }
    
    private function getDegreeLevelDescription($student_level, $program_level) {
        if ($student_level == $program_level) {
            return "Matches your degree level";
        }
        
        return "Different degree level";
    }
    
    private function getSubjectDescription($student_subject_id, $program_id) {
        if (!$student_subject_id) {
            return "No subject preference set";
        }
        
        $match = $this->db->fetchOne(
            "SELECT * FROM program_subjects 
             WHERE program_id = ? AND subject_id = ?",
            [$program_id, $student_subject_id]
        );
        
        if ($match) {
            return "Matches your subject interest";
        }
        
        return "Different subject focus";
    }
    
    private function getDeadlineDescription($deadline) {
        if (!$deadline) {
            return "Rolling admission";
        }
        
        $now = new DateTime();
        $deadline_date = new DateTime($deadline);
        $days = $now->diff($deadline_date)->days;
        
        if ($days < 0) {
            return "Deadline passed";
        }
        
        return "$days days until deadline";
    }
}

