<?php
session_start();

if(!isset($_POST['username']) || !isset($_POST['password'])){
    die("Brak danych logowania");
}

$user = $_POST['username'];
$pass = $_POST['password'];

$link = mysqli_connect("127.0.0.1","dm81079_z20","Dawidek7003#","dm81079_z20");

if(!$link){
    die("Błąd połączenia: ".mysqli_connect_errno()." ".mysqli_connect_error());
}

mysqli_set_charset($link, "utf8");

// =====================
// LOGOWANIE USERA
// =====================
$sql = "SELECT * FROM users WHERE username = ?";
$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, "s", $user);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$rekord = mysqli_fetch_assoc($result);

$ip = $_SERVER['REMOTE_ADDR'];

if($rekord && $rekord['password'] == $pass){

    $_SESSION['loggedin'] = true;
    $_SESSION['username'] = $rekord['username'];
    $_SESSION['user_id'] = $rekord['id'];
    
     // 🔥 ADMIN SESSION
    $_SESSION['is_admin'] = ($rekord['username'] === 'admin' && $rekord['password'] === 'admin');



    header("Location: index.php"); 
    exit();
}
else {
    echo "Niepoprawny login lub hasło!";
}

mysqli_close($link);
?>