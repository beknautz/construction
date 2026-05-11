<?php
// ONE-TIME ADMIN SETUP — delete this file immediately after running
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

$name     = 'Admin User';
$email    = 'admin@constructionos.com';
$password = 'admin123';
$hash     = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

$db = get_db();

// Remove old bad record if exists, then insert fresh
$db->prepare('DELETE FROM users WHERE email = ?')->execute([$email]);
$db->prepare('INSERT INTO users (name, email, password, role, is_active) VALUES (?, ?, ?, ?, 1)')
   ->execute([$name, $email, $hash, 'admin']);

echo '<p style="font-family:sans-serif;color:green;font-size:1.2rem;">
    <strong>Admin user created.</strong><br>
    Email: ' . htmlspecialchars($email) . '<br>
    Password: ' . htmlspecialchars($password) . '<br><br>
    <strong style="color:red">Delete setup-admin.php from the server now, then <a href="/login.php">log in</a>.</strong>
</p>';
