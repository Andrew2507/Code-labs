<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit;
}

$action = $_GET['action'] ?? '';
$userId = $_SESSION['user_id'];

switch ($action) {
    case 'get_user_stats':
        $stmt = $db->query("SELECT COUNT(*) as total_tasks FROM tasks");
        $totalTasks = $stmt->fetch(PDO::FETCH_ASSOC)['total_tasks'];

        $stmt = $db->prepare("SELECT COUNT(*) as solved_tasks 
                      FROM user_tasks 
                      WHERE user_id = ? AND status = 'Решена'");
        $stmt->execute([$userId]);
        $solvedTasks = $stmt->fetch(PDO::FETCH_ASSOC)['solved_tasks'];

        $stmt = $db->prepare("SELECT rating FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $rating = $stmt->fetch(PDO::FETCH_ASSOC)['rating'];

        echo json_encode([
            'success' => true,
            'total_tasks' => (int)$totalTasks,
            'solved_tasks' => (int)$solvedTasks,
            'rating' => (int)$rating,
            'progress_percentage' => $totalTasks > 0 ? round(($solvedTasks / $totalTasks) * 100) : 0
        ]);
        break;
    case 'get_check_status':
        $submissionId = $_GET['submission_id'] ?? 0;
        $stmt = $db->prepare("SELECT status, result, message FROM submissions WHERE id = ?");
        $stmt->execute([$submissionId]);
        $submission = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$submission) {
            echo json_encode(['success' => false, 'error' => 'Submission not found']);
            exit;
        }

        if ($submission['status'] === 'В обработке') {
            $stmt = $db->prepare("SELECT TIMESTAMPDIFF(MINUTE, submitted_at, NOW()) as minutes_passed FROM submissions WHERE id = ?");
            $stmt->execute([$submissionId]);
            $timeCheck = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($timeCheck['minutes_passed'] > 5) {
                updateSubmissionStatus($submissionId, 'Ошибка', null, 'Проверка заняла слишком много времени');
                $submission['status'] = 'Ошибка';
                $submission['message'] = 'Проверка заняла слишком много времени';
            }
        }

        echo json_encode([
            'success' => true,
            'status' => $submission['status'],
            'result' => $submission['result'],
            'message' => $submission['message']
        ]);
        break;
    case 'get_submissions':
        $taskId = $_GET['task_id'] ?? 0;
        $submissions = getSubmissions($userId, $taskId);

        ob_start();
        if (empty($submissions)): ?>
            <p>Вы еще не отправляли решения для этой задачи.</p>
        <?php else: ?>
            <div class="list-group">
                <?php foreach ($submissions as $submission): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <strong><?= $submission['submitted_at'] ?></strong>
                            <span class="badge bg-<?= getSubmissionStatusBadgeClass($submission['status']) ?>">
                                <?= $submission['status'] ?>
                            </span>
                        </div>
                        <?php if ($submission['status'] === 'Проверено'): ?>
                            <div class="mt-2">
                                <p class="mb-1"><strong>Результат:</strong> <?= $submission['result'] ?></p>
                                <?php if ($submission['message']): ?>
                                    <p class="mb-0"><strong>Сообщение:</strong> <?= $submission['message'] ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif;
        $html = ob_get_clean();

        echo json_encode(['success' => true, 'html' => $html]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);
}