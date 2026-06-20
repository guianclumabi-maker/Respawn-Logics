<?php
require_once __DIR__ . '/bootstrap/app.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service - Respawn Logics</title>
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
        <h1>Terms of Service & Data Processing Agreement</h1>
        <p>Effective Date: <?= date('F j, Y') ?></p>
        <p>Welcome to Respawn Logics. By accessing or using our services, you (the Tenant) agree to be bound by these Terms of Service.</p>
        
        <h2>1. Use of Our Services</h2>
        <p>You must follow any policies made available to you within the Services. You may use our Services only as permitted by law, including the Philippine Data Privacy Act of 2012 (RA 10173).</p>
        
        <h2>2. Data Processing Agreement (DPA)</h2>
        <p>This section constitutes the Data Processing Agreement between Respawn Logics (the Personal Information Processor) and you (the Personal Information Controller).
        <ul>
            <li>Respawn Logics agrees to process personal data only on the documented instructions of the Tenant.</li>
            <li>We ensure that persons authorized to process the personal data have committed themselves to confidentiality.</li>
            <li>We take all measures required pursuant to the security of processing under RA 10173.</li>
            <li>We do not access or mine your employee records for any purpose other than providing the agreed-upon platform services. Support access is granted strictly on an explicit, consent-based opt-in.</li>
        </ul>
        </p>

        <h2>3. Account Responsibilities</h2>
        <p>You are responsible for safeguarding the passwords of your users and for obtaining explicit consent from your employees before encoding their data into the platform.</p>
        
        <br>
        <a href="<?= url('/') ?>">&larr; Back to Home</a>
    </div>
</body>
</html>
