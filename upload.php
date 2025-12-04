<?php 
if ($_SERVER["REQUEST_METHOD"] == "POST") {

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
           RAILWAY FIX — use system python, not a venv
        -------------------------------------------------------------*/
        $pythonExe = "python3";

        $processScript = escapeshellarg(__DIR__ . "/process_pdf.py");

        $command = "$pythonExe $processScript "
                 . escapeshellarg($targetFilePath)
                 . " give summary 2>&1";

        // Run Python
        $output = shell_exec($command);

        /* ------------------------------------------------------------
           WRITE DEBUG LOG IN RAILWAY-SAFE LOCATION (/tmp)
        -------------------------------------------------------------*/
        file_put_contents(
            "/tmp/debug_output.txt",
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

