<?php
declare(strict_types=1);

/** Delete a managed record and its references. Keep an inactive QR as an ID reservation. */
function handleManagementDeletion(string $kind): void
{
    $action = $kind === 'students' ? 'delete_student' : 'delete_team';
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST[$action])) return;
    requireAdminUser();
    if (!is_string($_POST['csrf_token'] ?? null) || empty($_SESSION['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) redirectToError(403, 'csrf');
    $field = $kind === 'students' ? 'original_id' : 'id';
    $id = is_string($_POST[$field] ?? null) ? $_POST[$field] : '';
    $records = repository($kind)->all();
    if ($id === '' || findById($records, $id) === null) redirectToError(404);
    $relationKey = $kind === 'students' ? 'student_id' : 'team_id';
    $mapping = repository('mapping')->all();
    $featured = $kind === 'students' ? repository('featured_students')->all() : null;
    $qr = repository('qr_codes')->all();
    $qrId = ($kind === 'students' ? 'student-' : 'team-') . $id;
    $qr = array_values(array_filter($qr, static fn(array $item): bool => (string) ($item['id'] ?? '') !== $qrId));
    $qr[] = ['id' => $qrId, 'label' => '削除済み', 'target_url' => '', 'is_active' => false, 'updated_at' => date(DATE_ATOM)];
    repository('qr_codes')->replaceAll($qr);
    repository('mapping')->replaceAll(array_values(array_filter($mapping, static fn(array $item): bool => (string) ($item[$relationKey] ?? '') !== $id)));
    if ($featured !== null) repository('featured_students')->replaceAll(array_values(array_filter($featured, static fn(array $item): bool => (string) ($item['student_id'] ?? '') !== $id)));
    repository($kind)->replaceAll(array_values(array_filter($records, static fn(array $item): bool => (string) ($item['id'] ?? '') !== $id)));
    header('Location: ' . url($kind === 'students' ? 'admin.php' : 'teams_admin.php') . '?deleted=1');
    exit;
}

function reservedManagementIds(string $prefix): array
{
    $ids = [];
    foreach (repository('qr_codes')->all() as $item) {
        $id = (string) ($item['id'] ?? '');
        if (strpos($id, $prefix) === 0) $ids[] = ['id' => substr($id, strlen($prefix))];
    }
    return $ids;
}
