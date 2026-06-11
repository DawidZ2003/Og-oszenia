<?php
session_start();

$user = $_POST['user'];
$pass = $_POST['pass'];
$pass2 = $_POST['pass2'];

$link = mysqli_connect("127.0.0.1","dm81079_z20","Dawidek7003#","dm81079_z20");

if(!$link){
    die("Błąd połączenia");
}

mysqli_set_charset($link, "utf8");

// =====================
// WALIDACJA
// =====================
if ($user === '' || $pass === '' || $pass2 === '') {
    exit("Wypełnij wszystkie pola.");
}

if ($pass !== $pass2){
    exit("Hasła nie są identyczne.");
}

// =====================
// SPRAWDZENIE USERA
// =====================
$sql = "SELECT id FROM users WHERE username = ?";
$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, "s", $user);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) > 0){
    exit("Użytkownik istnieje.");
}

// =====================
// DODANIE USERA
// =====================
$sql = "INSERT INTO users (username, password) VALUES (?, ?)";
$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, "ss", $user, $pass);
mysqli_stmt_execute($stmt);

$user_id = mysqli_insert_id($link);

header("Location: logowanie.php");
exit();
?>