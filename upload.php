<?php 
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (isset($_FILES["file"])) {

        $maxSize = 10 * 1024 * 1024; // 10 MB limit

        if ($_FILES["file"]["size"] > $maxSize) {
            echo json_encode([
                "status" => "error",
                "message" => "❌ File too large. Max allowed is 10 MB."
            ]);
            exit;
        }

        // ----------- IMPORTANT: Railway-safe writable folders -----------
        $baseTmp = "/tmp"; 
        $uploadDir = $baseTmp . "/uploads/";
        $summaryDir = $baseTmp . "/saved_summaries/";
        $logFile = $baseTmp . "/debug_output.txt";

        // Create folders if missing
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        if (!is_dir($summaryDir)) mkdir($summaryDir, 0777, true);

        // ---------------------------------------------------------------

        $fileName = basename($_FILES["file"]["name"]);
        $targetFilePath = $uploadDir . $fileName;

        $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
        $saveChoice = $_POST["saveChoice"] ?? "yes";

        if ($fileType !== "pdf") {
            echo json_encode([
                "status" => "error",
                "message" => "Only PDF files are allowed."
            ]);
            exit;
        }

        if ($_FILES["file"]["error"] !== 0) {
            echo json_encode([
                "status" => "error",
                "message" => "Upload error: " . $_FILES["file"]["error"]
            ]);
            exit;
        }

        // Move file to /tmp/uploads
        if (!move_uploaded_file($_FILES["file"]["tmp_name"], $targetFilePath)) {
            echo json_encode([
                "status" => "error",
                "message" => "Failed to upload file."
            ]);
            exit;
        }

        // ------------------ RUN PYTHON (system python3) -----------------
        $pythonExe = "python3";
        $processScript = escapeshellarg(__DIR__ . "/process_pdf.py");

        $command = "$pythonExe $processScript " . escapeshellarg($targetFilePath) . " 'give summary' 2>&1";

        $output = shell_exec($command);

        // Log raw output to /tmp/debug_output.txt
        file_put_contents(
            $logFile,
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

        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode([
                "status" => "error",
                "message" => "Could not parse JSON from Python output.",
                "raw_output" => $output
            ]);
            exit;
        }

        if (!isset($decoded["summary"]) || trim($decoded["summary"]) === "") {
            echo json_encode([
                "status" => "error",
                "message" => "⚠ Summary is empty — check debug_output.txt",
                "raw_output" => $output
            ]);
            exit;
        }

        // ---------------- DELETE IF NOT SAVING -------------------------
        $pdfHash = md5_file($targetFilePath);
        $cacheFile = $summaryDir . "$pdfHash.json";

        if ($saveChoice === "no") {
            if (file_exists($targetFilePath)) unlink($targetFilePath);
            if (file_exists($cacheFile)) unlink($cacheFile);
        }

        // Return success
        echo json_encode([
            "status" => "success",
            "filename" => $fileName,
            "summary" => $decoded["summary"],
            "cached" => $decoded["cached"] ?? false,
            "saved" => $saveChoice
        ]);
        exit;
    }

    echo json_encode([
        "status" => "error",
        "message" => "No file uploaded."
    ]);
    exit;

} else {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid request."
    ]);
}
?>
