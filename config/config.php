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
    $outPut.='<div class="alert alert-success">'.htmlspecialchars($_SESSION['success']).'</div>';
    unset($_SESSION['success']);
  }
  if(isset($_SESSION['error'])){
    $outPut.='<div class="alert alert-danger">'.htmlspecialchars($_SESSION['error']).'</div>';
    unset($_SESSION['error']);
  }
  return $outPut;
}

function redirect($uri){
  $uri = trim($uri, '/');
  header("Location: " . base_url($uri));
  exit();
}

function is_valid_room_location($location)
{
    $location = trim($location);

    // Locations are address-like ("Baneshwor, Kathmandu", "House 45, Baneshwor",
    // "Koteshwor-32, Kathmandu"). Reject bare numbers such as "123456" or "99999".
    if (preg_match_all('/[a-zA-Z]/', $location) < 3) {
        return false;
    }

    // Reject repetition-only strings such as "aaaa".
    $letters = preg_replace('/[^a-zA-Z]/', '', $location);
    if (strlen($letters) >= 3 && substr_count($letters, $letters[0]) === strlen($letters)) {
        return false;
    }

    return true;
}

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function verify_csrf()
{
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function is_human_readable_room_title($title)
{
    $title = trim($title);

    // Reject numeric-only titles such as "12345" or "999999".
    if (preg_match_all('/[a-zA-Z]/', $title) < 3) {
        return false;
    }

    // Reject repetition-only strings such as "aaaa" or "aaaa11111" where the
    // letter content is a single repeated character and not a readable word.
    $letters = preg_replace('/[^a-zA-Z]/', '', $title);
    if (strlen($letters) >= 3 && substr_count($letters, $letters[0]) === strlen($letters)) {
        return false;
    }

    // Split into alphanumeric chunks, ignoring spaces and punctuation.
    $tokens = preg_split('/[^a-zA-Z0-9]+/', $title, -1, PREG_SPLIT_NO_EMPTY);

    // A lone mixed token such as "abc123456" looks like a code/ID rather than a
    // readable title. Shorthand like "2BHK", "A1", or "Room A1" stays allowed.
    if (count($tokens) === 1 && preg_match('/[a-zA-Z]/', $tokens[0]) && preg_match('/\d{3,}/', $tokens[0])) {
        return false;
    }

    return true;
}
?>
