<?php
require_once __DIR__.'/../includes/auth.php';
secure_session();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success'=>false,'message'=>'POST required.'],405);
verify_csrf();
rate_limit('report', 5);
$reason = $_POST['reason'] ?? '';
$details = trim($_POST['details'] ?? '');
if (!in_array($reason,['spam','malware','copyright','illegal','other'],true)) json_response(['success'=>false,'message'=>'Choose a valid reason.'],422);
$stmt = db()->prepare('SELECT id FROM images WHERE short_id = ? AND status != "deleted"');
$stmt->execute([$_POST['id'] ?? '']);
$imageId = $stmt->fetchColumn();
if (!$imageId) json_response(['success'=>false,'message'=>'Image not found.'],404);
db()->prepare('INSERT INTO reports(image_id,reason,details,ip_hash) VALUES(?,?,?,?)')->execute([$imageId,$reason,substr($details,0,1000),hash('sha256',client_ip())]);
json_response(['success'=>true]);
