<?php


if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    redirect('/auth/login');
    exit;
}

