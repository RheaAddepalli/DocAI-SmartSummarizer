<?php
session_start();

// ---------------------------------------
// LOGIN CHECK
// ---------------------------------------
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// ---------------------------------------
// RECORD PAGE ACCESS (ACTIVE USER LOG)
// ---------------------------------------
$visitFile = __DIR__ . "/logs/active_users.txt";
$time = date("Y-m-d H:i:s");
$ip = $_SERVER['REMOTE_ADDR'];

file_put_contents(
    $visitFile,
    "VISIT | IP: $ip | TIME: $time\n",
    FILE_APPEND
);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF Summarizer</title>

    <style>
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            text-align: center;
            margin: 40px;
            background: linear-gradient(135deg, #e0f7fa, #f8f9fa);
        }
        h1 {
            color: #00695c;
            margin-bottom: 10px;
        }
        input[type="file"], select, button {
            padding: 10px;
            margin: 10px;
            font-size: 16px;
            border-radius: 8px;
            border: 1px solid #ccc;
            outline: none;
        }
        button {
            background-color: #26a69a;
            color: white;
            border: none;
            cursor: pointer;
            transition: 0.3s;
        }
        button:hover {
            background-color: #00796b;
        }
        #summaryOutput {
            margin-top: 30px;
            font-weight: bold;
            color: #004d40;
            white-space: pre-wrap;
            background: #e0f2f1;
            padding: 15px;
            border-radius: 10px;
            width: 80%;
            margin-left: auto;
            margin-right: auto;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        #loadingMessage {
            margin-top: 10px;
            font-weight: bold;
            color: #f57c00;
            display: none;
        }
        label {
            color: #00796b;
            font-weight: bold;
        }
        a.logout {
            position: absolute;
            top: 20px;
            right: 30px;
            text-decoration: none;
            color: white;
            background-color: #dc3545;
            padding: 8px 15px;
            border-radius: 5px;
        }
    </style>
</head>

<body>

    <a href="logout.php" class="logout">Logout</a>

    <h1>📄 Smart PDF Summarizer</h1>

    <!-- Upload PDF -->
    <input type="file" id="pdfUpload" accept="application/pdf">
    <br>

    <!-- Ask if user wants to save -->
    <label for="saveChoice">Do you want to save this PDF in our database for faster summaries next time ?</label>

    <select id="saveChoice">
        <option value="yes">✅ Yes, save it</option>
        <option value="no">❌ No, delete after summary</option>
    </select>

    <br>

    <button onclick="uploadPDF()">Upload & Summarize</button>

    <p id="loadingMessage">⚙ Processing your PDF, please wait...</p>

    <h2>Summary</h2>
    <p id="summaryOutput">Waiting for file upload...</p>

    <script>
        function uploadPDF() {
            const fileInput = document.getElementById("pdfUpload");
            const pdfFile = fileInput.files[0];
            const saveChoice = document.getElementById("saveChoice").value;

            if (!pdfFile) {
                alert("Please select a PDF file first.");
                return;
            }

            const formData = new FormData();
            formData.append("file", pdfFile);
            formData.append("saveChoice", saveChoice);

            const loading = document.getElementById("loadingMessage");
            const output = document.getElementById("summaryOutput");

            loading.style.display = "block";
            output.innerText = "";

            fetch("upload.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                loading.style.display = "none";

                if (data.status === "success") {
                    output.innerText = data.summary;
                } else {
                    output.innerText = "❌ " + (data.message || "An error occurred.");
                }
            })
            .catch(error => {
                loading.style.display = "none";
                output.innerText = "Error uploading or summarizing file.";
            });
        }
    </script>

</body>

</html>
