<?php
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'tenant') {
    redirect('/auth/login');
    exit;
}

