<?php
session_start();
ob_start(); 

function base_url($uri=""){
    $uri = trim($uri, '/');
      $http_s=$_SERVER['REQUEST_SCHEME'];
      $serverName=$_SERVER['HTTP_HOST'];
     return $http_s.'://'.$serverName.'/roomfinder/'.$uri;
}

function messages(){
  $outPut="";
  if(isset($_SESSION['success'])){
    $outPut.='<div class="alert alert-success">'.$_SESSION['success'].'</div>';
    unset($_SESSION['success']);  
  }
  if(isset($_SESSION['error'])){
    $outPut.='<div class="alert alert-danger">'.$_SESSION['error'].'</div>';
    unset($_SESSION['error']);
  }
  return $outPut;
}

function redirect($uri){
  $uri = trim($uri, '/');
  header("Location: " . base_url($uri));
  exit();
}
?>
 