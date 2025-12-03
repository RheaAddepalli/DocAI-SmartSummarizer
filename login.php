<?php
session_start();

/* -------------------------
   LOAD ACCESS CODE FROM .env
-------------------------- */
$envPath = __DIR__ . "/.env";

if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (!str_contains($line, '=')) continue;

        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

$ACCESS_CODE = $_ENV["ACCESS_CODE"] ?? "demo@2226";

/* -------------------------
        CHECK CODE
-------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered = trim($_POST['access_code']);

    if ($entered === $ACCESS_CODE) {
        $_SESSION['loggedin'] = true;
        header("Location: index.php");
        exit;
    } else {
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
