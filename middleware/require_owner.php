<?php

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'owner') {
    redirect('/auth/login');
    exit;
}

