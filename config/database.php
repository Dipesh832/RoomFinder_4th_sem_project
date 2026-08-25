<?php
$conn = mysqli_connect("localhost", "root", "", "roomfinderDB");

if (!$conn) {
    die("Database connection failed:" . mysqli_connect_error());
}


?>