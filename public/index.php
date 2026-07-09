<?php
require_once '../app/includes/init.php';
$page_title = "Home";
require_once '../app/templates/header.php';

$auth = new Auth();
$user = $auth->getCurrentUser();
$db = new Database();

// Fetch real stats from the database
$stats = $db->fetchOne("
    SELECT 
        (SELECT COUNT(*) FROM students) as students,
        (SELECT COUNT(*) FROM universities WHERE deleted_at IS NULL) as universities,
        (SELECT COUNT(*) FROM programs WHERE deleted_at IS NULL) as programs,
        (SELECT COUNT(*) FROM scholarships) as scholarships
");

// Sample testimonials (could be fetched from a testimonials table if you have one)
$testimonials = [
    [
        'name' => 'Emily Chen',
        'country' => 'China',
        'text' => 'StudySphere helped me find the perfect program in Canada. The application tracking was a lifesaver!',
        'image' => 'https://randomuser.me/api/portraits/women/44.jpg',
        'program' => 'MSc Computer Science'
    ],
    [
        'name' => 'Raj Patel',
        'country' => 'India',
        'text' => 'I got into my dream university in the UK thanks to the scholarship search feature. Highly recommended!',
        'image' => 'https://randomuser.me/api/portraits/men/32.jpg',
        'program' => 'MBA'
    ],
    [
        'name' => 'Maria Garcia',
        'country' => 'Spain',
        'text' => 'The visa tracker kept me organized through the whole process. I’m now studying in Australia!',
        'image' => 'https://randomuser.me/api/portraits/women/68.jpg',
        'program' => 'Bachelor of Engineering'
    ]
];
?>

<!-- Hero Section -->
<section class="hero">
    <div class="hero-content">
        <h1 class="hero-title">Your Gateway to <span class="highlight">Global Education</span></h1>
        <p class="hero-subtitle">Discover, apply, and track your study abroad journey – all in one place.</p>
        <?php if (!$user): ?>
            <div class="hero-buttons">
                <a href="register.php" class="btn btn-primary btn-large"><i class="fas fa-user-plus"></i> Get Started</a>
                <a href="login.php" class="btn btn-outline-gold btn-large"><i class="fas fa-sign-in-alt"></i> Login</a>
            </div>
        <?php else: ?>
            <div class="hero-buttons">
                <a href="<?= ($_SESSION['role'] == 'student') ? 'student/dashboard.php' : (($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'super_admin') ? 'admin/dashboard.php' : 'counselor/dashboard.php') ?>" class="btn btn-primary btn-large"><i class="fas fa-tachometer-alt"></i> Go to Dashboard</a>
            </div>
        <?php endif; ?>
    </div>
    <div class="hero-wave">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="#ffffff" fill-opacity="1" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,154.7C960,171,1056,181,1152,170.7C1248,160,1344,128,1392,112L1440,96L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>
    </div>
</section>

<!-- Stats Section -->
<section class="stats-section">
    <div class="container">
        <div class="stats-grid">
            <div class="stat-item">
                <i class="fas fa-user-graduate stat-icon"></i>
                <div class="stat-content">
                    <h3 class="stat-number"><?= number_format($stats['students'] ?? 0) ?></h3>
                    <p class="stat-label">Students Helped</p>
                </div>
            </div>
            <div class="stat-item">
                <i class="fas fa-university stat-icon"></i>
                <div class="stat-content">
                    <h3 class="stat-number"><?= number_format($stats['universities'] ?? 0) ?></h3>
                    <p class="stat-label">Partner Universities</p>
                </div>
            </div>
            <div class="stat-item">
                <i class="fas fa-graduation-cap stat-icon"></i>
                <div class="stat-content">
                    <h3 class="stat-number"><?= number_format($stats['programs'] ?? 0) ?></h3>
                    <p class="stat-label">Programs Available</p>
                </div>
            </div>
            <div class="stat-item">
                <i class="fas fa-award stat-icon"></i>
                <div class="stat-content">
                    <h3 class="stat-number"><?= number_format($stats['scholarships'] ?? 0) ?></h3>
                    <p class="stat-label">Scholarships</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="features-section">
    <div class="container">
        <div class="section-header">
            <h2>Everything You Need in One Place</h2>
            <p class="text-muted">Simplify your study abroad journey with our powerful tools</p>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-search"></i>
                </div>
                <h3>Find Programs</h3>
                <p>Explore thousands of programs from top universities worldwide, filtered by your preferences.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <h3>Manage Applications</h3>
                <p>Track all your applications in one dashboard, from submission to decision.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-upload"></i>
                </div>
                <h3>Upload Documents</h3>
                <p>Securely store and manage transcripts, recommendation letters, and more.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3>Track Progress</h3>
                <p>Real‑time status updates and milestones for every application.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-briefcase"></i>
                </div>
                <h3>Part‑time Jobs</h3>
                <p>Discover job opportunities that fit your schedule and career goals.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-passport"></i>
                </div>
                <h3>Visa Tracker</h3>
                <p>Stay on top of visa deadlines, requirements, and application status.</p>
            </div>
        </div>
    </div>
</section>

<!-- How It Works -->
<section class="how-it-works">
    <div class="container">
        <div class="section-header">
            <h2>Your Journey in Four Simple Steps</h2>
            <p class="text-muted">From search to acceptance – we guide you every step of the way</p>
        </div>
        <div class="steps-grid">
            <div class="step-item">
                <div class="step-number">1</div>
                <div class="step-icon"><i class="fas fa-user-plus"></i></div>
                <h3>Create Account</h3>
                <p>Sign up for free and set up your profile with your academic background.</p>
            </div>
            <div class="step-item">
                <div class="step-number">2</div>
                <div class="step-icon"><i class="fas fa-university"></i></div>
                <h3>Find Programs</h3>
                <p>Search and shortlist programs that match your interests and budget.</p>
            </div>
            <div class="step-item">
                <div class="step-number">3</div>
                <div class="step-icon"><i class="fas fa-paper-plane"></i></div>
                <h3>Apply & Track</h3>
                <p>Submit applications, upload documents, and monitor progress.</p>
            </div>
            <div class="step-item">
                <div class="step-number">4</div>
                <div class="step-icon"><i class="fas fa-check-circle"></i></div>
                <h3>Get Accepted</h3>
                <p>Receive decisions, apply for visas, and prepare for your adventure.</p>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials -->
<section class="testimonials-section">
    <div class="container">
        <div class="section-header">
            <h2>Success Stories</h2>
            <p class="text-muted">Hear from students who achieved their dreams with StudySphere</p>
        </div>
        <div class="testimonials-grid">
            <?php foreach ($testimonials as $t): ?>
                <div class="testimonial-card">
                    <div class="testimonial-avatar">
                        <img src="<?= $t['image'] ?>" alt="<?= htmlspecialchars($t['name']) ?>">
                    </div>
                    <div class="testimonial-content">
                        <p class="testimonial-text">"<?= htmlspecialchars($t['text']) ?>"</p>
                        <div class="testimonial-author">
                            <strong><?= htmlspecialchars($t['name']) ?></strong>
                            <span><?= htmlspecialchars($t['country']) ?> • <?= htmlspecialchars($t['program']) ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Motivational Quote -->
<section class="quote-section">
    <div class="container">
        <blockquote class="quote-card">
            <i class="fas fa-quote-left quote-icon"></i>
            <p>Education is the most powerful weapon which you can use to change the world.</p>
            <footer>— Nelson Mandela</footer>
        </blockquote>
    </div>
</section>

<!-- CTA Banner (for non‑logged‑in users) -->
<?php if (!$user): ?>
    <section class="cta-section">
        <div class="container">
            <div class="cta-card">
                <h2>Ready to Start Your Journey?</h2>
                <p>Join thousands of students who have found their dream programs through StudySphere.</p>
                <a href="register.php" class="btn btn-primary btn-large">Create Your Free Account</a>
            </div>
        </div>
    </section>
<?php endif; ?>

<style>
/* Home page specific styles – reuse global variables from main.css */

/* Hero Section */
.hero {
    position: relative;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 5rem 2rem;
    text-align: center;
    overflow: hidden;
}
.hero-content {
    max-width: 800px;
    margin: 0 auto;
    position: relative;
    z-index: 2;
}
.hero-title {
    font-size: 3rem;
    font-weight: 700;
    margin-bottom: 1rem;
    animation: fadeInUp 0.8s ease;
}
.hero-title .highlight {
    background: linear-gradient(145deg, #ffd700, #ffb347);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.hero-subtitle {
    font-size: 1.3rem;
    margin-bottom: 2rem;
    opacity: 0.9;
    animation: fadeInUp 1s ease;
}
.hero-buttons {
    animation: fadeInUp 1.2s ease;
}
/* New golden outline button */
.btn-outline-gold {
    background: transparent;
    border: 2px solid #ffd700;
    color: #ffd700;
    border-radius: 30px;
    padding: 0.75rem 2rem;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
}
.btn-outline-gold:hover {
    background: #ffd700;
    color: #2c3e50; /* dark contrast */
    border-color: #ffd700;
}
.btn-primary {
    background: linear-gradient(145deg, #667eea, #764ba2);
    border: none;
    border-radius: 30px;
    padding: 0.75rem 2rem;
    color: white;
    font-weight: 500;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(102,126,234,0.3);
}
.btn-large {
    padding: 0.9rem 2.5rem;
    font-size: 1.1rem;
}
.hero-wave {
    position: absolute;
    bottom: -1px;
    left: 0;
    width: 100%;
    line-height: 0;
    z-index: 1;
}
.hero-wave svg {
    display: block;
    width: 100%;
    height: auto;
}

/* Stats Section */
.stats-section {
    background: white;
    padding: 3rem 0;
    margin-top: -1px; /* overlap wave */
}
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 2rem;
}
.stat-item {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    padding: 1.5rem;
    background: #f8fafc;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.02);
    transition: transform 0.2s;
}
.stat-item:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.06);
}
.stat-icon {
    font-size: 2.5rem;
    color: #3498db;
}
.stat-number {
    font-size: 2rem;
    font-weight: 700;
    margin: 0;
    color: #2c3e50;
}
.stat-label {
    margin: 0;
    color: #7f8c8d;
    font-size: 0.9rem;
}

/* Section Headers */
.section-header {
    text-align: center;
    margin-bottom: 3rem;
}
.section-header h2 {
    font-size: 2.2rem;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 0.5rem;
}
.section-header p {
    font-size: 1.1rem;
}

/* Features Section */
.features-section {
    padding: 4rem 0;
    background: #f8fafc;
}
.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
}
.feature-card {
    background: white;
    border-radius: 24px;
    padding: 2rem;
    text-align: center;
    box-shadow: 0 8px 20px rgba(0,0,0,0.02);
    transition: all 0.3s;
}
.feature-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 30px rgba(0,0,0,0.06);
}
.feature-icon {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: linear-gradient(145deg, #667eea, #764ba2);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    margin: 0 auto 1.5rem;
}
.feature-card h3 {
    font-size: 1.3rem;
    margin-bottom: 0.8rem;
    color: #2c3e50;
}
.feature-card p {
    color: #6c757d;
    line-height: 1.6;
}

/* How It Works */
.how-it-works {
    padding: 4rem 0;
    background: white;
}
.steps-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 2rem;
}
.step-item {
    text-align: center;
    position: relative;
}
.step-number {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #3498db;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.2rem;
    margin: 0 auto 1rem;
}
.step-icon {
    font-size: 2.5rem;
    color: #667eea;
    margin-bottom: 1rem;
}
.step-item h3 {
    font-size: 1.2rem;
    margin-bottom: 0.5rem;
    color: #2c3e50;
}
.step-item p {
    color: #6c757d;
    font-size: 0.95rem;
    line-height: 1.5;
}

/* Testimonials */
.testimonials-section {
    padding: 4rem 0;
    background: #f8fafc;
}
.testimonials-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
}
.testimonial-card {
    background: white;
    border-radius: 24px;
    padding: 2rem;
    box-shadow: 0 8px 20px rgba(0,0,0,0.02);
    transition: transform 0.2s;
}
.testimonial-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.06);
}
.testimonial-avatar {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    overflow: hidden;
    margin: 0 auto 1rem;
    border: 3px solid #667eea;
}
.testimonial-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.testimonial-content {
    text-align: center;
}
.testimonial-text {
    font-style: italic;
    color: #4a5568;
    margin-bottom: 1rem;
}
.testimonial-author {
    font-weight: 500;
}
.testimonial-author span {
    display: block;
    font-size: 0.85rem;
    color: #7f8c8d;
    margin-top: 0.2rem;
}

/* Quote Section – Golden & Rounded */
.quote-section {
    padding: 4rem 0;
    background: white; /* keep section background white */
}
.quote-card {
    background: linear-gradient(135deg, #ffd700, #ffb347);
    border-radius: 40px; /* extra round */
    padding: 3rem;
    color: white;
    max-width: 800px;
    margin: 0 auto;
    box-shadow: 0 20px 40px rgba(255,215,0,0.3);
    text-align: center;
}
.quote-icon {
    font-size: 2.5rem;
    opacity: 0.5;
    margin-bottom: 1rem;
}
.quote-card p {
    font-size: 1.8rem;
    font-weight: 300;
    line-height: 1.5;
}
.quote-card footer {
    margin-top: 1rem;
    font-size: 1.1rem;
    opacity: 0.8;
}

/* CTA Section */
.cta-section {
    padding: 4rem 0;
    background: white;
}
.cta-card {
    background: linear-gradient(145deg, #667eea, #764ba2);
    color: white;
    border-radius: 30px;
    padding: 4rem;
    text-align: center;
    box-shadow: 0 20px 40px rgba(102,126,234,0.3);
}
.cta-card h2 {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
}
.cta-card p {
    font-size: 1.2rem;
    margin-bottom: 2rem;
    opacity: 0.9;
}
.cta-card .btn-primary {
    background: white;
    color: #667eea;
    border: none;
}
.cta-card .btn-primary:hover {
    transform: scale(1.05);
    box-shadow: 0 8px 16px rgba(255,255,255,0.2);
}

/* Animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive */
@media (max-width: 768px) {
    .hero-title {
        font-size: 2.2rem;
    }
    .hero-subtitle {
        font-size: 1.1rem;
    }
    .section-header h2 {
        font-size: 1.8rem;
    }
    .cta-card {
        padding: 2rem;
    }
    .cta-card h2 {
        font-size: 2rem;
    }
    .quote-card {
        padding: 2rem;
    }
    .quote-card p {
        font-size: 1.4rem;
    }
}
</style>

<?php require_once '../app/templates/footer.php'; ?>