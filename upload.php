<?php 
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    file_put_contents(__DIR__."/railway_test.txt", "PHP can write files!\n", FILE_APPEND);


    if (isset($_FILES["file"])) {

        $maxSize = 10 * 1024 * 1024; // 10 MB

        if ($_FILES["file"]["size"] > $maxSize) {
            echo json_encode([
                "status" => "error",
                "message" => "❌ File too large. Maximum allowed size is 10 MB."
            ]);
            exit;
        }

        $targetDir = __DIR__ . "/uploads/";
        $fileName = basename($_FILES["file"]["name"]);
        $targetFilePath = $targetDir . $fileName;
        $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
        $saveChoice = $_POST["saveChoice"] ?? "yes";

        // Allow only PDFs
        if ($fileType !== "pdf") {
            echo json_encode([
                "status" => "error",
                "message" => "Only PDF files are allowed."
            ]);
            exit;
        }

        // Ensure upload folder exists
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        // Upload errors
        if ($_FILES["file"]["error"] !== 0) {
            echo json_encode([
                "status" => "error",
                "message" => "Upload error: " . $_FILES["file"]["error"]
            ]);
            exit;
        }

        // Move uploaded file
        if (!move_uploaded_file($_FILES["file"]["tmp_name"], $targetFilePath)) {
            echo json_encode([
                "status" => "error",
                "message" => "Failed to move uploaded file."
            ]);
            exit;
        }

        /* ------------------------------------------------------------
           UNIVERSAL PYTHON DETECTION (Local Windows + Railway Linux)
        -------------------------------------------------------------*/
        $pythonExe = "python3"; // Railway

        if (stripos(PHP_OS, "WIN") !== false) {
            $pythonExe = __DIR__ . "/venv_gai_new/Scripts/python.exe";
        }

        $pythonExe = escapeshellarg($pythonExe);

        $processScript = escapeshellarg(__DIR__ . "/process_pdf.py");

        $command = "$pythonExe $processScript "
                 . escapeshellarg($targetFilePath)
                 . " give summary 2>&1";

        // Run Python
        $output = shell_exec($command);

        // Debug log
        file_put_contents(
            __DIR__ . "/debug_output.txt",
            "COMMAND: $command\n\nRAW OUTPUT:\n" . $output . "\n"
        );

        // Trim BOM
        $output = trim($output, "\xEF\xBB\xBF \n\r\t");

        // Extract JSON
        if (preg_match('/\{.*\}/s', $output, $matches)) {
            $json_part = $matches[0];
            $decoded = json_decode($json_part, true);
        } else {
            $decoded = json_decode($output, true);
        }

        // JSON fail
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode([
                "status" => "error",
                "message" => "Could not parse JSON from Python output.",
                "raw_output" => $output
            ]);
            exit;
        }

        // empty summary
        if (!isset($decoded["summary"]) || trim($decoded["summary"]) === "") {
            echo json_encode([
                "status" => "error",
                "message" => "⚠ Summary is empty — check debug_output.txt for details.",
                "raw_output" => $output
            ]);
            exit;
        }

        /* ------------------------------------------------------------
           DELETE PDF + CACHED SUMMARY IF saveChoice = "no"
        -------------------------------------------------------------*/
        $pdfHash = md5_file($targetFilePath);
        $cacheFile = __DIR__ . "/saved_summaries/$pdfHash.json";

        if ($saveChoice === "no") {
            if (file_exists($targetFilePath)) unlink($targetFilePath);
            if (file_exists($cacheFile)) unlink($cacheFile);
        }

        // Response
        echo json_encode([
            "status" => "success",
            "filename" => $fileName,
            "summary" => $decoded["summary"],
            "cached" => $decoded["cached"] ?? false,
            "saved" => $saveChoice
        ]);

    } else {
        echo json_encode([
            "status" => "error",
            "message" => "No file uploaded."
        ]);
    }

} else {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid request."
    ]);
}
?>
