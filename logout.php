<?php
require __DIR__ . '/inc.php';
if (user()) audit('logout', user()['username']);
$_SESSION = [];
session_destroy();
redirect('login.php');
