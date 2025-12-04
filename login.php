<?php
session_start();

/* -------------------------
   LOAD ACCESS CODE FROM .env (LOCAL)
-------------------------- */
$envPath = __DIR__ . "/.env";

if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;

        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

/* -----------------------------------------
   UPDATED ACCESS CODE LOADING (IMPORTANT)
   - Railway → getenv("ACCESS_CODE")
   - Local → .env
   - No default fallback (secure)
----------------------------------------- */
$ACCESS_CODE = getenv("ACCESS_CODE");

if (!$ACCESS_CODE) {
    // fallback to local .env
    $ACCESS_CODE = $_ENV["ACCESS_CODE"] ?? "";
}

if (!$ACCESS_CODE) {
    die("❌ Server Error: ACCESS_CODE not configured.");
}

/* -------------------------
        CHECK CODE
-------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered = trim($_POST['access_code']);
    $ip = $_SERVER['REMOTE_ADDR'];
    $time = date("Y-m-d H:i:s");

    if ($entered === $ACCESS_CODE) {

        // Log successful login
        $logFile = __DIR__ . "/logs/access_log.txt";
        file_put_contents(
            $logFile,
            "LOGIN SUCCESS | IP: $ip | TIME: $time\n",
            FILE_APPEND
        );

        $_SESSION['loggedin'] = true;
        header("Location: index.php");
        exit;

    } else {
        // Log failed login
        $logFile = __DIR__ . "/logs/access_log.txt";
        file_put_contents(
            $logFile,
            "LOGIN FAIL | IP: $ip | TIME: $time | ENTERED: $entered\n",
            FILE_APPEND
        );

        $error = "Invalid access code!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Secure Access | PDF Summarizer</title>

<style>
body {
    display:flex; justify-content:center; align-items:center;
    min-height:100vh;
    background:linear-gradient(135deg,#89f7fe,#66a6ff);
    font-family:'Poppins',sans-serif;
}

form {
    background:#fff; padding:30px; border-radius:15px;
    box-shadow:0 5px 20px rgba(0,0,0,0.2);
    text-align:center; width:320px;
}

input {
    display:block; width:100%; padding:12px;
    margin:15px 0; border-radius:10px; border:1px solid #ccc;
}

button {
    padding:12px; width:100%;
    border:none; border-radius:10px;
    background:linear-gradient(90deg,#0072ff,#00c6ff);
    color:#fff; cursor:pointer; font-size:16px;
}

p.error { color:red; font-weight:bold; }
</style>
</head>

<body>

<form method="POST">
    <h2>Enter Access Code</h2>
    
    <input type="password" name="access_code" placeholder="Access Code" required>
    
    <button type="submit">Access</button>

    <?php if(isset($error)) echo "<p class='error'>$error</p>"; ?>
</form>

</body>
</html>
