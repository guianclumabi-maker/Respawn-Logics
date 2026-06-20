<?php
require_once __DIR__ . '/bootstrap/app.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - Respawn Logics</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #0b0f1a; color: #fff; font-family: 'Space Grotesk', sans-serif; margin: 0; padding: 40px; }
        .container { max-width: 800px; margin: 0 auto; background: #141929; padding: 40px; border-radius: 12px; }
        h1 { color: #00e07a; }
        a { color: #4f8ef7; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Privacy Policy</h1>
        <p>Effective Date: <?= date('F j, Y') ?></p>
        <p>Your privacy is important to us. This Privacy Policy explains how Respawn Logics collects, uses, and protects your information, in compliance with the <strong>Data Privacy Act of 2012 (Republic Act No. 10173)</strong> of the Philippines.</p>
        
        <h2>1. Role Definitions</h2>
        <p>Under the DPA, the employer (Tenant) acts as the <strong>Personal Information Controller (PIC)</strong>, determining the purposes for which data is processed. Respawn Logics acts as the <strong>Personal Information Processor (PIP)</strong>, processing employee data strictly upon the instructions of the PIC.</p>

        <h2>2. Rights of the Data Subject</h2>
        <p>In accordance with RA 10173, you (the employee or applicant) are entitled to the following rights:</p>
        <ul>
            <li><strong>Right to be informed:</strong> You have the right to know how your data is collected and processed.</li>
            <li><strong>Right to object:</strong> You can withhold consent to the processing of your data.</li>
            <li><strong>Right to access:</strong> You may request access to your personal information stored on our platform.</li>
            <li><strong>Right to rectification:</strong> You have the right to dispute the inaccuracy or error in your personal data.</li>
            <li><strong>Right to erasure or blocking:</strong> You have the right to suspend, withdraw, or order the blocking/removal of your personal data.</li>
            <li><strong>Right to damages:</strong> You may claim compensation if you suffered damages due to false, incomplete, or unauthorized use of personal data.</li>
            <li><strong>Right to data portability:</strong> You have the right to obtain a copy of your data in an electronic or structured format.</li>
        </ul>

        <h2>3. Data Protection Officer (DPO)</h2>
        <p>If you have any questions or concerns regarding our privacy practices, you may contact our designated Data Protection Officer at: <a href="mailto:dpo@respawnlogics.com">dpo@respawnlogics.com</a></p>
        
        <br>
        <a href="<?= url('/') ?>">&larr; Back to Home</a>
    </div>
</body>
</html>
