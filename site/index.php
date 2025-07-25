<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

$isLoggedIn = isLoggedIn();
$user = $isLoggedIn ? getUserById($_SESSION['user_id']) : null;

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login'])) {
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);

        $user = authenticate($email, $password);
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Неверный email или пароль';
        }
    } elseif (isset($_POST['register'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);

        if (registerUser($username, $email, $password)) {
            $_SESSION['user_id'] = $db->lastInsertId();
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Ошибка регистрации. Возможно, пользователь уже существует.';
        }
    }
}

$currentYear = date('Y');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CodeLabs - Профессиональная платформа для проверки кода</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4a6bff;
            --primary-dark: #3a56cc;
            --dark-color: #2d3748;
            --light-color: #f8fafc;
            --gray-color: #64748b;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--light-color);
            color: var(--dark-color);
            line-height: 1.6;
        }

        .navbar {
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            letter-spacing: -0.5px;
        }

        .hero-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 5rem 0 4rem;
            position: relative;
            overflow: hidden;
        }

        @media (min-width: 992px) {
            .hero-section {
                padding: 6rem 0 5rem;
            }
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiPjxkZWZzPjxwYXR0ZXJuIGlkPSJwYXR0ZXJuIiB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHBhdHRlcm5Vbml0cz0idXNlclNwYWNlT25Vc2UiIHBhdHRlcm5UcmFuc2Zvcm09InJvdGF0ZSg0NSkiPjxyZWN0IHdpZHRoPSIyMCIgaGVpZ2h0PSIyMCIgZmlsbD0icmdiYSgyNTUsMjU1LDI1NSwwLjAzKSIvPjwvcGF0dGVybj48L2RlZnM+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0idXJsKCNwYXR0ZXJuKSIvPjwvc3ZnPg==');
        }

        .section-title {
            position: relative;
            padding-bottom: 1rem;
            margin-bottom: 2rem;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: var(--primary-color);
            border-radius: 2px;
        }

        .feature-card {
            border: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            height: 100%;
            background-color: white;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 1.5rem;
        }

        @media (min-width: 768px) {
            .feature-card {
                padding: 2rem;
                margin-bottom: 0;
            }
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .feature-icon {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
            background: rgba(74, 107, 255, 0.1);
            width: 60px;
            height: 60px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        @media (min-width: 768px) {
            .feature-icon {
                font-size: 2.5rem;
                width: 80px;
                height: 80px;
                margin-bottom: 1.5rem;
            }
        }

        .badge-future {
            background-color: #f59e0b;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 0.35rem 0.7rem;
            border-radius: 50px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-left: 0.5rem;
            vertical-align: middle;
        }

        .btn {
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(74, 107, 255, 0.3);
        }

        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-lg {
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            border-radius: 10px;
        }

        @media (min-width: 768px) {
            .btn-lg {
                padding: 1rem 2rem;
                font-size: 1.1rem;
            }
        }

        .auth-modal .nav-tabs {
            border-bottom: none;
            margin-bottom: 1.5rem;
            justify-content: center;
        }

        .auth-modal .nav-link {
            border: none;
            color: var(--gray-color);
            font-weight: 500;
            padding: 0.5rem 1rem;
            position: relative;
        }

        @media (min-width: 768px) {
            .auth-modal .nav-link {
                padding: 0.75rem 1.5rem;
            }
        }

        .auth-modal .nav-link.active {
            color: var(--primary-color);
            background: none;
        }

        .auth-modal .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 50%;
            transform: translateX(-50%);
            width: 50%;
            height: 3px;
            background: var(--primary-color);
            border-radius: 3px;
        }

        .stats-item {
            text-align: center;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        @media (min-width: 768px) {
            .stats-item {
                padding: 2rem;
                margin-bottom: 0;
            }
        }

        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        @media (min-width: 768px) {
            .stats-number {
                font-size: 2.5rem;
            }
        }

        .stats-label {
            color: var(--gray-color);
            font-size: 0.9rem;
            font-weight: 500;
        }

        @media (min-width: 768px) {
            .stats-label {
                font-size: 1rem;
            }
        }

        .error-message {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1100;
            max-width: 90%;
            width: 400px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            border: none;
            border-left: 4px solid #ef4444;
        }

        .footer {
            background-color: var(--dark-color);
            color: white;
            padding: 3rem 0 2rem;
        }

        @media (min-width: 768px) {
            .footer {
                padding: 4rem 0 2rem;
            }
        }

        .footer-links {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: center;
            margin-bottom: 1.5rem;
        }

        .footer-links a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: color 0.2s;
            font-size: 0.9rem;
        }

        @media (min-width: 768px) {
            .footer-links {
                gap: 1.5rem;
                margin-bottom: 2rem;
            }
            .footer-links a {
                font-size: 1rem;
            }
        }

        .footer-links a:hover {
            color: white;
        }

        .social-links {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            margin-bottom: 1.5rem;
        }

        .social-links a {
            color: white;
            background: rgba(255,255,255,0.1);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        @media (min-width: 768px) {
            .social-links {
                gap: 1rem;
                margin-bottom: 2rem;
            }
            .social-links a {
                width: 40px;
                height: 40px;
            }
        }

        .social-links a:hover {
            background: var(--primary-color);
            transform: translateY(-2px);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-color);
        }

        .dropdown-menu {
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-radius: 10px;
            padding: 0.5rem;
        }

        .dropdown-item {
            border-radius: 6px;
            padding: 0.5rem 1rem;
            font-weight: 500;
        }

        .dropdown-item:hover {
            background-color: rgba(74, 107, 255, 0.1);
            color: var(--primary-color);
        }

        .dropdown-divider {
            margin: 0.25rem 0;
        }

        .hero-image {
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            max-width: 100%;
            height: auto;
        }

        .hero-content h1 {
            font-size: 2.25rem;
            line-height: 1.2;
        }

        @media (min-width: 768px) {
            .hero-content h1 {
                font-size: 2.75rem;
            }
        }

        @media (min-width: 992px) {
            .hero-content h1 {
                font-size: 3.5rem;
            }
        }

        .lead {
            font-size: 1.1rem;
        }

        @media (min-width: 768px) {
            .lead {
                font-size: 1.25rem;
            }
        }

        @media (min-width: 992px) {
            .hero-image {
                width: 50%;
                height: auto;
                margin-left: 50%;
            }
        }
    </style>
</head>
<body>

<nav class="navbar navbar-light bg-white sticky-top">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <img src="logo.png" alt="CodeLabs" height="40" class="d-inline-block align-top me-2">
            <span style="color: var(--primary-color);">Code</span>Labs
        </a>
        <div class="ms-auto">
            <?php if ($isLoggedIn): ?>
                <a href="/dashboard.php" class="btn btn-primary">
                    Начать
                </a>
            <?php else: ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#authModal">
                    Начать
                </button>
            <?php endif; ?>
        </div>
    </div>
</nav>

<section class="hero-section">
    <div class="container position-relative">
        <div class="row align-items-center">
            <div class="col-lg-6 order-lg-1 order-2 hero-content">
                <h1 class="fw-bold mb-4">Профессиональная платформа для проверки кода</h1>
                <p class="lead mb-4 opacity-75">Мгновенная проверка решений с детализированной обратной связью. Совершенствуйте свои навыки программирования с CodeLabs.</p>
                <div class="d-flex gap-3 flex-wrap">
                    <?php if ($isLoggedIn): ?>
                        <a href="dashboard.php" class="btn btn-light btn-lg px-4 fw-medium">
                            Перейти в панель управления
                        </a>
                    <?php else: ?>
                        <button class="btn btn-light btn-lg px-4 fw-medium" data-bs-toggle="modal" data-bs-target="#authModal">
                            Начать бесплатно
                        </button>
                    <?php endif; ?>
                    <a href="#features" class="btn btn-outline-light btn-lg px-4">Узнать больше</a>
                </div>
            </div>
            <div class="col-lg-6 order-lg-2 order-1 mb-4 mb-lg-0">
                <div class="position-relative">
                    <img src="logo.png" alt="CodeLabs" class="img-fluid hero-image" style="width: 50%; height: auto;">
                </div>
            </div>
        </div>
    </div>
</section>

<section id="features" class="py-5">
    <div class="container py-5">
        <div class="text-center mb-5">
            <h2 class="display-5 fw-bold section-title d-inline-block">Наши преимущества</h2>
            <p class="lead text-muted mx-auto" style="max-width: 700px;">Почему ведущие учебные заведения и разработчики выбирают CodeLabs</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <h3 class="h4 mb-3">Мгновенная проверка</h3>
                    <p class="text-muted mb-0">Среднее время проверки решения - менее 5 секунд благодаря оптимизированной инфраструктуре</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-code"></i>
                    </div>
                    <h3 class="h4 mb-3">10+ языков <span class="badge-future">скоро</span></h3>
                    <p class="text-muted mb-0">Поддержка популярных языков программирования: Python, Java, C++, JavaScript и других</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-comment-alt"></i>
                    </div>
                    <h3 class="h4 mb-3">Детальная аналитика <br> <span class="badge-future">в разработке</span></h3>
                    <p class="text-muted mb-0">Понятные сообщения об ошибках с примерами исправлений и рекомендациями</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3 class="h4 mb-3">Безопасность</h3>
                    <p class="text-muted mb-0">Изолированная среда выполнения для каждого решения гарантирует защиту данных</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-brain"></i>
                    </div>
                    <h3 class="h4 mb-3">AI-анализ <span class="badge-future">в разработке</span></h3>
                    <p class="text-muted mb-0">Умные подсказки и персонализированные рекомендации по улучшению кода</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-language"></i>
                    </div>
                    <h3 class="h4 mb-3">Новые языки <span class="badge-future">скоро</span></h3>
                    <p class="text-muted mb-0">Расширенная поддержка дополнительных языков программирования</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5 bg-light">
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-md-3 col-6">
                <div class="stats-item">
                    <div class="stats-number">10+ <span class="badge-future" style="color: black">скоро</span></div>
                    <div class="stats-label">Языков программирования</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stats-item">
                    <div class="stats-number">&lt;5с</div>
                    <div class="stats-label">Среднее время проверки</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stats-item">
                    <div class="stats-number">99.9%</div>
                    <div class="stats-label">Доступность сервиса</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stats-item">
                    <div class="stats-number">24/7</div>
                    <div class="stats-label">Поддержка</div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5">
    <div class="container py-5">
        <div class="text-center mb-5">
            <h2 class="display-5 fw-bold section-title d-inline-block">Для кого создан CodeLabs?</h2>
            <p class="lead text-muted mx-auto" style="max-width: 700px;">Платформа, которая адаптируется под ваши потребности</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="feature-card h-100">
                    <div class="feature-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <h3 class="h4 mb-3">Студенты</h3>
                    <ul class="text-start ps-4 text-muted">
                        <li class="mb-2">Подготовка к экзаменам и зачетам</li>
                        <li class="mb-2">Практика перед стажировкой</li>
                        <li class="mb-2">Участие в соревнованиях по программированию</li>
                        <li>Освоение новых технологий</li>
                    </ul>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card h-100">
                    <div class="feature-icon">
                        <i class="fas fa-briefcase"></i>
                    </div>
                    <h3 class="h4 mb-3">Разработчики</h3>
                    <ul class="text-start ps-4 text-muted">
                        <li class="mb-2">Подготовка к техническим собеседованиям</li>
                        <li class="mb-2">Прокачка алгоритмических навыков</li>
                        <li class="mb-2">Изучение новых языков программирования</li>
                        <li>Подготовка к сертификациям</li>
                    </ul>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card h-100">
                    <div class="feature-icon">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <h3 class="h4 mb-3">Преподаватели</h3>
                    <ul class="text-start ps-4 text-muted">
                        <li class="mb-2">Автоматическая проверка заданий</li>
                        <li class="mb-2">Готовые наборы задач для курсов</li>
                        <li class="mb-2">Мониторинг прогресса студентов</li>
                        <li>Интеграция с учебными программами</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5 bg-primary text-white">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8 text-center">
                <h2 class="display-5 fw-bold mb-4">Готовы улучшить свои навыки программирования?</h2>
                <p class="lead mb-4 opacity-75">Присоединяйтесь к тысячам разработчиков, которые уже используют CodeLabs для профессионального роста</p>
                <?php if ($isLoggedIn): ?>
                    <a href="dashboard.php" class="btn btn-light btn-lg px-5 fw-medium">
                        Перейти в панель управления
                    </a>
                <?php else: ?>
                    <button class="btn btn-light btn-lg px-5 fw-medium" data-bs-toggle="modal" data-bs-target="#authModal">
                        Начать бесплатно
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Модальное окно авторизации -->
<?php if (!$isLoggedIn): ?>
    <div class="modal fade" id="authModal" tabindex="-1" aria-labelledby="authModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fs-4" id="authModalLabel">Добро пожаловать в CodeLabs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-0">
                    <ul class="nav nav-tabs" id="authTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#login" type="button" role="tab">Вход</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="register-tab" data-bs-toggle="tab" data-bs-target="#register" type="button" role="tab">Регистрация</button>
                        </li>
                    </ul>

                    <div class="tab-content pt-4" id="authTabsContent">
                        <!-- Форма входа -->
                        <div class="tab-pane fade show active" id="login" role="tabpanel" aria-labelledby="login-tab">
                            <form method="POST" action="index.php">
                                <input type="hidden" name="login" value="1">
                                <div class="mb-3">
                                    <label for="loginEmail" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="loginEmail" name="email" required>
                                </div>
                                <div class="mb-3">
                                    <label for="loginPassword" class="form-label">Пароль</label>
                                    <input type="password" class="form-control" id="loginPassword" name="password" required>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="rememberMe" name="remember">
                                        <label class="form-check-label" for="rememberMe">Запомнить меня</label>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 py-2">Войти</button>
                            </form>
                        </div>

                        <div class="tab-pane fade" id="register" role="tabpanel" aria-labelledby="register-tab">
                            <form method="POST" action="index.php">
                                <input type="hidden" name="register" value="1">
                                <div class="mb-3">
                                    <label for="registerUsername" class="form-label">Имя пользователя</label>
                                    <input type="text" class="form-control" id="registerUsername" name="username" required>
                                </div>
                                <div class="mb-3">
                                    <label for="registerEmail" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="registerEmail" name="email" required>
                                </div>
                                <div class="mb-3">
                                    <label for="registerPassword" class="form-label">Пароль</label>
                                    <input type="password" class="form-control" id="registerPassword" name="password" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 py-2">Зарегистрироваться</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<footer class="footer">
    <div class="container">
        <div class="row">
            <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                <h3 class="h5">CodeLabs</h3>
                <p class="mb-2">Платформа для автоматической проверки решений задач по программированию</p>
                <p class="mb-0">© <?= $currentYear ?> code-labs.ru</p>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <p class="mb-2"><a href="mailto:partner@code-labs.ru" class="text-white">partner@code-labs.ru</a></p>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var authModal = document.getElementById('authModal');

        if (authModal) {
            authModal.addEventListener('show.bs.modal', function(event) {
                var button = event.relatedTarget;
                var targetTab = button.getAttribute('data-auth-tab');

                if (targetTab === 'register') {
                    var registerTab = new bootstrap.Tab(document.getElementById('register-tab'));
                    registerTab.show();
                }
            });
        }
    });
</script>
</body>
</html>