<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (isset($_FILES["file"])) {
    $maxSize = 20 * 1024 * 1024; // 20 MB size limit

    if ($_FILES["file"]["size"] > $maxSize) {
        echo json_encode([
            "status" => "error",
            "message" => "❌ File too large. Maximum allowed size is 20 MB ."
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

        // Handle upload errors
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

        // Absolute paths for Python
        $pythonExe = escapeshellarg(__DIR__ . "/venv_gai_new/Scripts/python.exe");
        $processScript = escapeshellarg(__DIR__ . "/process_pdf.py");

        // Build command with UTF-8 support and pass prompt
        $command = "chcp 65001 > nul && $pythonExe $processScript "
                 . escapeshellarg($targetFilePath) . " give summary";

        // Run Python and capture output
        $output = shell_exec($command . " 2>&1");

        // Log raw output for debugging
        file_put_contents(
            __DIR__ . "/debug_output.txt",
            "COMMAND: $command\n\nRAW OUTPUT:\n" . $output . "\n"
        );

        // Remove BOM and whitespace
        $output = trim($output, "\xEF\xBB\xBF \n\r\t");

        // Extract JSON part if warnings exist
        if (preg_match('/\{.*\}/s', $output, $matches)) {
            $json_part = $matches[0];
            $decoded = json_decode($json_part, true);
        } else {
            $decoded = json_decode($output, true);
        }

        // Handle JSON errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode([
                "status" => "error",
                "message" => "Could not parse JSON from Python output.",
                "raw_output" => $output
            ]);
            exit;
        }

        // Handle empty summary
        if (!isset($decoded["summary"]) || trim($decoded["summary"]) === "") {
            echo json_encode([
                "status" => "error",
                "message" => "⚠ Summary is empty — check debug_output.txt for details.",
                "raw_output" => $output
            ]);
            exit;
        }

       
    // DELETE PDF + CACHED SUMMARY if user selected "no"

    // STEP 1: compute PDF hash BEFORE deleting anything
    $pdfHash = md5_file($targetFilePath);
    $cacheFile = __DIR__ . "/saved_summaries/$pdfHash.json";

    if ($saveChoice === "no") {

        // STEP 2: delete the uploaded PDF
        if (file_exists($targetFilePath)) {
            unlink($targetFilePath);
        }

    // STEP 3: delete the saved summary for privacy
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }

      

        // Return JSON response
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
