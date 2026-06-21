<?php
require_once __DIR__ . '/fpdf/fpdf.php';

class PayslipGenerator
{
    public static function generate($data, $outputPath)
    {
        $pdf = new FPDF();
        $pdf->AddPage();
        
        // --- Header ---
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'RESPAWN LOGICS', 0, 1, 'C');
        
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 5, 'Official Employee Payslip', 0, 1, 'C');
        $pdf->Ln(10);
        
        // --- Employee Info & Run Info ---
        $pdf->SetFont('Arial', 'B', 10);
        
        // Col 1
        $pdf->Cell(35, 6, 'Employee Name:', 0, 0);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(65, 6, $data['employeeName'], 0, 0);
        
        // Col 2
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(35, 6, 'Pay Period:', 0, 0);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(55, 6, $data['period'], 0, 1);
        
        // Row 2
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(35, 6, 'Employee ID:', 0, 0);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(65, 6, $data['employeeId'], 0, 0);
        
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(35, 6, 'Pay Date:', 0, 0);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(55, 6, $data['payDate'], 0, 1);
        
        $pdf->Ln(10);
        
        // --- Tables ---
        $pdf->SetFillColor(230, 230, 230);
        $pdf->SetFont('Arial', 'B', 10);
        
        // Table Headers
        $pdf->Cell(90, 8, ' EARNINGS', 1, 0, 'L', true);
        $pdf->Cell(10, 8, '', 0, 0); // Spacer
        $pdf->Cell(90, 8, ' DEDUCTIONS', 1, 1, 'L', true);
        
        $pdf->SetFont('Arial', '', 10);
        
        $earnings = $data['earnings'] ?? [];
        $deductions = $data['deductions'] ?? [];
        $maxRows = max(count($earnings), count($deductions));
        
        $totalEarnings = 0;
        $totalDeductions = 0;
        
        for ($i = 0; $i < $maxRows; $i++) {
            // Earning Row
            if (isset($earnings[$i])) {
                $pdf->Cell(60, 7, ' ' . $earnings[$i]['description'], 'L', 0);
                $pdf->Cell(30, 7, number_format((float)$earnings[$i]['amount'], 2) . ' ', 'R', 0, 'R');
                $totalEarnings += (float)$earnings[$i]['amount'];
            } else {
                $pdf->Cell(90, 7, '', 'LR', 0);
            }
            
            $pdf->Cell(10, 7, '', 0, 0); // Spacer
            
            // Deduction Row
            if (isset($deductions[$i])) {
                $pdf->Cell(60, 7, ' ' . $deductions[$i]['description'], 'L', 0);
                $pdf->Cell(30, 7, number_format((float)$deductions[$i]['amount'], 2) . ' ', 'R', 1, 'R');
                $totalDeductions += (float)$deductions[$i]['amount'];
            } else {
                $pdf->Cell(90, 7, '', 'LR', 1);
            }
        }
        
        // Ensure bottom border if empty
        if ($maxRows === 0) {
            $pdf->Cell(90, 0, '', 'T', 0);
            $pdf->Cell(10, 0, '', 0, 0);
            $pdf->Cell(90, 0, '', 'T', 1);
        } else {
            // Draw a bottom line
            $y = $pdf->GetY();
            $pdf->Line(10, $y, 100, $y);
            $pdf->Line(110, $y, 200, $y);
        }
        
        $pdf->Ln(2);
        
        // --- Totals ---
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(60, 8, ' Total Earnings', 'LBT', 0);
        $pdf->Cell(30, 8, number_format($totalEarnings, 2) . ' ', 'RBT', 0, 'R');
        
        $pdf->Cell(10, 8, '', 0, 0); // Spacer
        
        $pdf->Cell(60, 8, ' Total Deductions', 'LBT', 0);
        $pdf->Cell(30, 8, number_format($totalDeductions, 2) . ' ', 'RBT', 1, 'R');
        
        $pdf->Ln(10);
        
        // --- Net Pay ---
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetFillColor(240, 255, 240);
        $pdf->Cell(130, 10, '', 0, 0);
        $pdf->Cell(30, 10, ' NET PAY:', 1, 0, 'L', true);
        $pdf->Cell(30, 10, number_format($data['netPay'], 2) . ' ', 1, 1, 'R', true);
        
        $pdf->Ln(15);
        
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->Cell(0, 5, 'This is a system-generated payslip. For discrepancies, please contact HR.', 0, 1, 'C');
        
        // Create directory if it doesn't exist
        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        
        $pdf->Output('F', $outputPath);
    }
}
