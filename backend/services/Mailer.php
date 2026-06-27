<?php

class Mailer {
    public static function send($toEmail, $toName, $subject, $htmlBody) {
        $apiKey = getenv('RESEND_API_KEY');
        $from = getenv('MAIL_FROM');

        if (!$apiKey || !$from) {
            $msg = "Mailer: RESEND_API_KEY or MAIL_FROM not set. Skipping email to $toEmail.";
            error_log($msg);
            throw new \RuntimeException($msg);
        }

        if (empty($toEmail)) {
            return false;
        }

        $data = [
            'from' => $from,
            'to' => [$toEmail],
            'subject' => $subject,
            'html' => $htmlBody
        ];

        $options = [
            'http' => [
                'header'  => "Content-Type: application/json\r\n" .
                             "Authorization: Bearer $apiKey\r\n",
                'method'  => 'POST',
                'content' => json_encode($data),
                'ignore_errors' => true // Don't throw exception on 4xx/5xx
            ]
        ];

        $context = stream_context_create($options);
        $result = @file_get_contents('https://api.resend.com/emails', false, $context);

        if ($result === false) {
            $msg = "Mailer: file_get_contents failed when sending to $toEmail.";
            error_log($msg);
            throw new \RuntimeException($msg);
        } else {
            $responseCode = $http_response_header[0] ?? '';
            if (strpos($responseCode, '200') === false && strpos($responseCode, '201') === false) {
                $msg = "Mailer: Resend API returned error for $toEmail: $result";
                error_log($msg);
                throw new \RuntimeException($msg);
            }
        }
        
        return true;
    }
}
