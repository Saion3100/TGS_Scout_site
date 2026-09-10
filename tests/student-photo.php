<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
function checkPhoto(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
checkPhoto(studentImageUrl(['id' => '12', 'photo_drive_file_id' => 'abc']) === url('student_photo.php') . '?id=12', 'Stable student URL');
checkPhoto(studentImageUrl(['id' => '12', 'photo_url' => '/old.jpg']) === '/old.jpg', 'Legacy URL retained');
checkPhoto(studentImageUrl(['id' => '12', 'icon_url' => '/icon.jpg']) === '/icon.jpg', 'Legacy icon retained');
checkPhoto(studentImageUrl(['id' => '12']) === '', 'No photo fallback');
try {
    StudentPhoto::metadata('../bad');
    throw new RuntimeException('Invalid ID accepted');
} catch (InvalidArgumentException $expected) {}
echo "Student photo checks passed.\n";

$managed = ['appProperties' => ['tgs_photo' => 'v1', 'student_id' => '12'], 'mimeType' => 'image/jpeg', 'driveId' => 'drive', 'parents' => ['folder']];
checkPhoto(StudentPhoto::isManagedPhoto($managed, '12', 'folder'), 'Managed photo eligible');
checkPhoto(!StudentPhoto::isManagedPhoto([], '12', 'folder'), 'Original photos protected');
checkPhoto(!StudentPhoto::isManagedPhoto($managed, '13', 'folder'), 'Other student protected');
checkPhoto(!StudentPhoto::isManagedPhoto($managed, '12', 'elsewhere'), 'Moved photo protected');
$legacy = $managed; unset($legacy['appProperties']);
checkPhoto(!StudentPhoto::isManagedPhoto($legacy, '12', 'folder'), 'Legacy upload protected');
$notImage = $managed; $notImage['mimeType'] = 'application/vnd.google-apps.folder';
checkPhoto(!StudentPhoto::isManagedPhoto($notImage, '12', 'folder'), 'Folder protected');
// These paths must return without credentials or any network requests.
StudentPhoto::trashPrevious([], ['id' => '12', 'photo_drive_file_id' => 'new'], []);
StudentPhoto::trashPrevious(['photo_drive_file_id' => 'same'], ['id' => '12', 'photo_drive_file_id' => 'same'], []);
StudentPhoto::trashPrevious(['photo_drive_file_id' => 'old'], ['id' => '12', 'photo_drive_file_id' => 'new'], [['photo_drive_file_id' => 'old']]);
StudentPhoto::trashPrevious(['photo_drive_file_id' => 'old'], ['id' => '12', 'photo_drive_file_id' => 'new'], [['photo_url' => 'https://drive.google.com/file/d/old/view']]);
echo "Photo cleanup protection checks passed.\n";
