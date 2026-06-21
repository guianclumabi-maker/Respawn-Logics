<?php

class Mailer {
    public static function send($toEmail, $toName, $subject, $htmlBody) {
        try {
            $apiKey = getenv('RESEND_API_KEY');
            $from = getenv('MAIL_FROM');

            if (!$apiKey || !$from) {
                error_log("Mailer: RESEND_API_KEY or MAIL_FROM not set. Skipping email to $toEmail.");
                return;
            }

            if (empty($toEmail)) {
                return;
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
                error_log("Mailer: file_get_contents failed when sending to $toEmail.");
            } else {
                $responseCode = $http_response_header[0] ?? '';
                if (strpos($responseCode, '200') === false && strpos($responseCode, '201') === false) {
                    error_log("Mailer: Resend API returned error for $toEmail: $result");
                }
            }
        } catch (Throwable $e) {
            error_log("Mailer: Exception when sending email to $toEmail - " . $e->getMessage());
        }
    }
}
