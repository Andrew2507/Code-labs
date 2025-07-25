<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$user = getCurrentUser();
$tasks = getTasks();
$usersRating = getUsersRating();
$submissions = getAllUserSubmissions($user['id']);

function getAllUserSubmissions($userId) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM submissions WHERE user_id = ? ORDER BY submitted_at DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$difficulty = $_GET['difficulty'] ?? '';
$status = $_GET['status'] ?? '';
$view = $_GET['view'] ?? '';

if ($difficulty || $status) {
    $tasks = array_filter($tasks, function($task) use ($difficulty, $status, $user) {
        $isSolved = isTaskSolved($user['id'], $task['id']);
        $taskStatus = $isSolved ? 'Решена' : $task['status'];

        return (!$difficulty || $task['difficulty'] == $difficulty) &&
            (!$status || $taskStatus == $status);
    });
}

function buildQueryString($params = []) {
    $currentParams = [
        'difficulty' => $_GET['difficulty'] ?? '',
        'status' => $_GET['status'] ?? '',
        'view' => $_GET['view'] ?? ''
    ];

    $mergedParams = array_merge($currentParams, $params);

    $filteredParams = array_filter($mergedParams, function($value) {
        return $value !== '';
    });

    return $filteredParams ? '?' . http_build_query($filteredParams) : '';
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель управления</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="icon" href="logo.png" type="image/png">
    <script src="/assets/highcharts.js"></script>
    <style>
        .h5, h5 {
            margin-bottom: 0 !important;
        }

        @media (max-width: 992px) {
            .flex-filer {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .flex-filer .flex-filer-1 {
                width: fit-content;
            }

            .flex-filer .mgcostule {
                margin-left: 29%;
            }
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="/">CodeLabs</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= (!isset($_GET['view']) || $_GET['view'] !== 'rating') ? 'active' : '' ?>" href="?">Задачи</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (isset($_GET['view']) && $_GET['view'] === 'rating') ? 'active' : '' ?>" href="<?= buildQueryString(['view' => 'rating']) ?>">Рейтинг</a>
                </li>
            </ul>
            <div class="d-flex align-items-center">
                <span class="text-light me-3"><?= $user['username'] ?> (Рейтинг: <?= $user['rating'] ?>)</span>
                <a href="logout.php" class="btn btn-outline-light">Выйти</a>
            </div>
        </div>
    </div>
</nav>

<?php if (!isset($_GET['view']) || $_GET['view'] !== 'rating'): ?>
    <div class="container mt-3">
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5>Статистика ошибок</h5>
                    </div>
                    <div class="card-body">
                        <div id="errorChart" style="height: 300px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5>Прогресс</h5>
                    </div>
                    <div class="card-body">
                        <h4 id="motivationalPhrase" class="text-primary mb-3"></h4>
                        <div class="progress mb-2" style="height: 30px;">
                            <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated"
                                 role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                            </div>
                        </div>
                        <p id="solvedCounter" class="text-center fw-bold mb-0"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="container mt-4">
    <?php if (!isset($_GET['view']) || $_GET['view'] !== 'rating'): ?>
        <div class="row mb-4 flex-filer">
            <div class="col-6">
                <h2>Список задач</h2>
            </div>
            <div class="col-12 col-md-6 flex-filer-1">
                <div class="d-flex flex-column flex-md-row justify-content-md-end gap-2">
                    <div class="dropdown mb-2 mb-md-0">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" id="difficultyFilter" data-bs-toggle="dropdown">
                            Сложность <?= $difficulty ? "($difficulty)" : '' ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-md-end">
                            <li><a class="dropdown-item" href="<?= buildQueryString(['difficulty' => '']) ?>">Все</a></li>
                            <li><a class="dropdown-item" href="<?= buildQueryString(['difficulty' => 'Легкая']) ?>">Легкая</a></li>
                            <li><a class="dropdown-item" href="<?= buildQueryString(['difficulty' => 'Средняя']) ?>">Средняя</a></li>
                            <li><a class="dropdown-item" href="<?= buildQueryString(['difficulty' => 'Сложная']) ?>">Сложная</a></li>
                        </ul>
                    </div>
                    <div class="dropdown mgcostule">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" id="statusFilter" data-bs-toggle="dropdown">
                            Статус <?= $status ? "($status)" : '' ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-md-end">
                            <li><a class="dropdown-item" href="<?= buildQueryString(['status' => '']) ?>">Все</a></li>
                            <li><a class="dropdown-item" href="<?= buildQueryString(['status' => 'Не решена']) ?>">Не решена</a></li>
                            <li><a class="dropdown-item" href="<?= buildQueryString(['status' => 'В процессе']) ?>">В процессе</a></li>
                            <li><a class="dropdown-item" href="<?= buildQueryString(['status' => 'Решена']) ?>">Решена</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <?php foreach ($tasks as $task):
                $isSolved = isTaskSolved($user['id'], $task['id']);
                ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 <?= $isSolved ? 'border-success' : '' ?>">
                        <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="badge bg-<?= getDifficultyBadgeClass($task['difficulty']) ?>">
                        <?= $task['difficulty'] ?>
                    </span>
                            <span class="badge bg-<?= $isSolved ? 'success' : getStatusBadgeClass($task['status']) ?>">
                        <?= $isSolved ? 'Решена' : $task['status'] ?>
                    </span>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title"><?= $task['title'] ?></h5>
                            <p class="card-text"><?= mb_substr($task['description'], 0, 100, 'UTF-8') ?>...</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Рейтинг: <?= $task['reward'] ?></span>
                                <a href="task.php?id=<?= $task['id'] ?>" class="btn btn-<?= $isSolved ? 'success' : 'primary' ?>">
                                    <?= $isSolved ? 'Просмотреть' : 'Решить' ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($tasks)): ?>
                <div class="col-12" style="margin-bottom: 3rem">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <h4 class="text-muted">Задачи не найдены</h4>
                            <p class="text-muted">Попробуйте изменить параметры фильтрации</p>
                            <a href="?" class="btn btn-primary mt-3">Сбросить фильтры</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <h2 class="mb-4">Рейтинг пользователей</h2>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Пользователь</th>
                    <th>Решенных задач</th>
                    <th>Рейтинг</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($usersRating as $index => $userRow): ?>
                    <tr class="<?= $userRow['id'] == $_SESSION['user_id'] ? 'table-primary' : '' ?>">
                        <td><?= $index + 1 ?></td>
                        <td><?= $userRow['username'] ?></td>
                        <td><?= $userRow['solved_tasks'] ?></td>
                        <td><?= $userRow['rating'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<footer class="footer">
    <div class="container">
        <div class="row">
            <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                <h3 class="h5">CodeLabs</h3>
                <p class="mb-2">Платформа для автоматической проверки решений задач по программированию</p>
                <p class="mb-0">© <?= date('Y') ?> code-labs.ru</p>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <p class="mb-2"><a href="mailto:partner@code-labs.ru" class="text-white">partner@code-labs.ru</a></p>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/script.js"></script>

<?php if (!isset($_GET['view']) || $_GET['view'] !== 'rating'): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const motivationalPhrases = [
                "Каждая ошибка — это шаг к совершенству!",
                "Ты ближе к решению, чем кажется!",
                "Успех складывается из маленьких усилий!",
                "Не сдавайся — ты почти у цели!",
                "Ошибки — ступеньки к мастерству!",
                "С каждой попыткой твой код становится лучше!",
                "Программирование — это магия, а ты — волшебник!",
                "Каждая решенная задача делает тебя сильнее!",
                "Ты способен на большее, чем думаешь!",
                "Код не работает? Это возможность научиться!"
            ];

            document.getElementById('motivationalPhrase').textContent =
                motivationalPhrases[Math.floor(Math.random() * motivationalPhrases.length)];

            const submissions = <?= json_encode($submissions) ?>;

            let errorStats = {
                'Успех': 0,
                'Неверный ответ': 0,
                'Ошибка выполнения': 0,
                'Таймаут': 0,
                'Другие ошибки': 0
            };

            submissions.forEach(sub => {
                if (sub.message) {
                    if (sub.message.includes('Задача решена успешно')) {
                        errorStats['Успех']++;
                    } else if (sub.message.includes('Не пройдены тесты')) {
                        const testsJson = sub.message.substring('Не пройдены тесты: '.length);
                        try {
                            const tests = JSON.parse(testsJson);
                            tests.forEach(test => {
                                if (test.status === 'wrong_answer') errorStats['Неверный ответ']++;
                                else if (test.status === 'runtime_error') errorStats['Ошибка выполнения']++;
                                else if (test.status === 'timeout') errorStats['Таймаут']++;
                                else errorStats['Другие ошибки']++;
                            });
                        } catch (e) {
                            errorStats['Другие ошибки']++;
                        }
                    } else {
                        errorStats['Другие ошибки']++;
                    }
                }
            });

            Highcharts.chart('errorChart', {
                chart: {
                    type: 'column'
                },
                title: {
                    text: 'Соотношение ошибок',
                    style: {
                        fontSize: '16px'
                    }
                },
                xAxis: {
                    type: 'category',
                    labels: {
                        rotation: -45,
                        style: {
                            fontSize: '12px'
                        }
                    }
                },
                yAxis: {
                    title: {
                        text: 'Количество'
                    },
                    allowDecimals: false
                },
                legend: {
                    enabled: false
                },
                tooltip: {
                    pointFormat: 'Количество: <b>{point.y}</b>'
                },
                series: [{
                    name: 'Ошибки',
                    colorByPoint: true,
                    data: Object.entries(errorStats).map(([name, count]) => ({
                        name: name,
                        y: count,
                        color: getColorForErrorType(name)
                    })),
                    dataLabels: {
                        enabled: false
                    }
                }],
                credits: {
                    enabled: false
                }
            });

            function getColorForErrorType(type) {
                const colors = {
                    'Успех': '#4bc0c0',
                    'Неверный ответ': '#ffce56',
                    'Ошибка выполнения': '#ff6384',
                    'Таймаут': '#9966ff',
                    'Другие ошибки': '#c9cbcf'
                };
                return colors[type] || '#999999';
            }

            fetch('api.php?action=get_user_stats')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const solved = data.solved_tasks;
                        const total = data.total_tasks;
                        const percentage = Math.round((solved/total)*100);

                        const progressBar = document.getElementById('progressBar');
                        progressBar.style.width = percentage + '%';
                        progressBar.setAttribute('aria-valuenow', percentage);
                        progressBar.textContent = percentage + '%';

                        if (percentage < 30) {
                            progressBar.classList.add('bg-danger');
                        } else if (percentage < 70) {
                            progressBar.classList.add('bg-warning');
                        } else {
                            progressBar.classList.add('bg-success');
                        }

                        document.getElementById('solvedCounter').textContent =
                            `Решено: ${solved} из ${total} задач`;
                    }
                })
                .catch(error => {
                    console.error('Error loading stats:', error);
                    document.getElementById('solvedCounter').textContent =
                        'Статистика временно недоступна';
                });
        });
    </script>
<?php endif; ?>

</body>
</html>