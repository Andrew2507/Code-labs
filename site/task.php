<?php

session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

$logDir = __DIR__ . '/logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: dashboard.php');
    exit;
}

$taskId = $_GET['id'];
$task = getTaskById($taskId);
$user = getCurrentUser();
$submissions = getSubmissions($user['id'], $taskId);
$isTaskSolved = isTaskSolved($user['id'], $taskId);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['solution'])) {
    $file = $_FILES['solution'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $fileName = uniqid() . '_' . $file['name'];
        $uploadDir = 'uploads/';

        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $uploadPath = $uploadDir . $fileName;

        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            $submissionId = submitSolution($user['id'], $taskId, $fileName);

            exec("php includes/check_background.php $submissionId $taskId $uploadPath > /dev/null &");

            header("Location: task.php?id=$taskId");
            exit;
        }
    }
}

function formatTestMessage($message, $aiFeedback = null) {
    $output = '';

    $isSuccess = strpos($message, 'Задача решена успешно') !== false;

    if ($isSuccess) {
        $output .= '<div class="alert alert-success">'.$message.'</div>';
    } else {
        if (!empty($aiFeedback)) {
            $output .= '<div class="ai-feedback">';
            $output .= '<h5><i class="bi bi-robot"></i> Советы от нейросети:</h5>';
            $output .= '<div class="ai-content">'.nl2br(htmlspecialchars($aiFeedback)).'</div>';
            $output .= '</div>';
        }
    }

    if (!$isSuccess && strpos($message, 'Не пройдены тесты:') === 0) {
        $testsJson = substr($message, strlen('Не пройдены тесты: '));
        $tests = json_decode($testsJson, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($tests)) {
            $html = '<div class="test-results mt-2">';
            $html .= '<h6>Результаты тестов:</h6>';
            $html .= '<div class="table-responsive"><table class="table table-sm table-bordered">';
            $html .= '<thead><tr><th>#</th><th>Статус</th><th>Ожидаемый</th><th>Полученный</th></tr></thead>';
            $html .= '<tbody>';

            foreach ($tests as $test) {
                $statusClass = '';
                if ($test['status'] === 'passed') {
                    $statusClass = 'table-success';
                } elseif ($test['status'] === 'runtime_error') {
                    $statusClass = 'table-danger';
                } elseif ($test['status'] === 'wrong_answer') {
                    $statusClass = 'table-warning';
                } elseif ($test['status'] === 'timeout') {
                    $statusClass = 'table-secondary';
                }

                $html .= '<tr class="' . $statusClass . '">';
                $html .= '<td>' . $test['test_number'] . '</td>';
                $html .= '<td>' . getTestStatusText($test['status']) . '</td>';
                $html .= '<td>' . htmlspecialchars($test['expected']) . '</td>';
                $html .= '<td>' . htmlspecialchars($test['actual']) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table></div></div>';
            $output .= $html;
        }
    } elseif (!$isSuccess) {
        $output .= nl2br(htmlspecialchars($message));
    }

    return $output;
}

function getTestStatusText($status) {
    $statuses = [
        'passed' => 'Пройден',
        'wrong_answer' => 'Неверный ответ',
        'runtime_error' => 'Ошибка выполнения',
        'timeout' => 'Превышено время',
    ];
    return $statuses[$status] ?? $status;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($task['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="./assets/css/style.css" rel="stylesheet">
    <link rel="icon" href="logo.png" type="image/png">
    <style>
        .ai-feedback {
            background-color: #f8f9fa;
            border-left: 4px solid #17a2b8;
            padding: 15px;
            margin-top: 15px;
            border-radius: 0 4px 4px 0;
        }
        .ai-feedback h5 {
            color: #17a2b8;
            margin-bottom: 10px;
        }
        .ai-content {
            background-color: white;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }

        p {
            margin-bottom: 0 !important;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="/dashboard.php">Система задач</a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">Назад к задачам</a>
                </li>
            </ul>
            <div class="d-flex align-items-center">
                <span class="text-light me-3"><?= htmlspecialchars($user['username']) ?> (Рейтинг: <?= $user['rating'] ?>)</span>
                <a href="logout.php" class="btn btn-outline-light">Выйти</a>
            </div>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4><?= htmlspecialchars($task['title']) ?></h4>
                    <div class="d-flex justify-content-end">
                        <span class="badge bg-<?= getDifficultyBadgeClass($task['difficulty']) ?> me-2">
                            <?= htmlspecialchars($task['difficulty']) ?>
                        </span>
                        <span class="badge bg-<?= $isTaskSolved ? 'success' : getStatusBadgeClass($task['status']) ?>">
                            <?= $isTaskSolved ? 'Решена' : htmlspecialchars($task['status']) ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <p><?= nl2br(htmlspecialchars($task['description'])) ?></p>
                    <hr>
                    <h5>Награда за решение: +<?= $task['reward'] ?> рейтинга</h5>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5>Отправить решение</h5>
                </div>
                <div class="card-body">
                    <?php if (!$isTaskSolved): ?>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="solution" class="form-label">Файл с решением</label>
                                <input class="form-control" type="file" id="solution" name="solution" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Отправить</button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-success">Вы уже решили эту задачу!</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5>Мои отправки</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($submissions)): ?>
                        <p>Вы еще не отправляли решения для этой задачи.</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($submissions as $submission): ?>
                                <div class="list-group-item <?= $submission['status'] === 'Проверено' && strpos($submission['message'], 'Задача решена успешно') !== false ? 'list-group-item-success' : ($submission['status'] === 'Проверено' ? 'list-group-item-warning' : '') ?>">
                                    <div class="d-flex justify-content-between">
                                        <strong><?= htmlspecialchars($submission['submitted_at']) ?></strong>
                                        <span class="badge bg-<?= getSubmissionStatusBadgeClass($submission['status']) ?>">
                                            <?= htmlspecialchars($submission['status']) ?>
                                        </span>
                                    </div>
                                    <?php if ($submission['status'] === 'Проверено'): ?>
                                        <div class="mt-2">
                                            <?php if ($submission['result']): ?>
                                                <p class="mb-1"><strong>Результат:</strong> <?= htmlspecialchars($submission['result']) ?></p>
                                            <?php endif; ?>
                                            <?php if ($submission['message']): ?>
                                                <div class="mb-0"><strong>Сообщение:</strong>
                                                    <?= formatTestMessage($submission['message'], $submission['ai_feedback']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="footer" style="margin-top: 3rem">
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
<script>
    function checkSubmissionStatus(submissionId) {
        fetch(`api.php?action=get_check_status&submission_id=${submissionId}`)
            .then(response => {
                if (!response.ok) throw new Error('Network error');
                return response.json();
            })
            .then(data => {
                if (!data.success) throw new Error('API error');

                console.log('Status check:', data.status);

                if (data.status === 'Проверено' || data.status === 'Ошибка') {
                    location.reload();
                } else if (data.status === 'В обработке') {
                    setTimeout(() => checkSubmissionStatus(submissionId), 3000);
                } else {
                    console.error('Unknown status:', data.status);
                }
            })
            .catch(error => {
                console.error('Status check failed:', error);
                setTimeout(() => checkSubmissionStatus(submissionId), 5000);
            });
    }

    <?php if (!empty($submissions) && in_array($submissions[0]['status'], ['В обработке', 'Обрабатывается'])): ?>
    document.addEventListener('DOMContentLoaded', function() {
        checkSubmissionStatus(<?= $submissions[0]['id'] ?>);
    });
    <?php endif; ?>
</script>
</body>
</html>