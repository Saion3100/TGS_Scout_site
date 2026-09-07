<?php
 declare(strict_types=1);
 require_once is_file(__DIR__ . '/src/bootstrap.php') ? __DIR__ . '/src/bootstrap.php' : dirname(__DIR__) . '/src/bootstrap.php';
 session_start();
 header('X-Robots-Tag: noindex, nofollow, noarchive', true);
 if (empty($_SESSION['admin_authenticated'])) { header('Location: ' . url('admin.php')); exit; }
 $teamId = isset($_GET['team_id']) && is_string($_GET['team_id']) ? $_GET['team_id'] : '';
 header('Location: ' . url('teams_admin.php') . ($teamId !== '' ? '?id=' . rawurlencode($teamId) . '#project-members' : ''));
 exit;