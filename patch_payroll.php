<?php
$file = 'C:/xampp/htdocs/respawn-logics/backend/controllers/PayrollController.php';
$content = file_get_contents($file);

$replacements = [
    "case 'finalize_run':\n                    if (!\$this->canManagePayroll())" => "case 'finalize_run':\n                    if (!\$this->canApprovePayroll())",
    "case 'sync_leave':\n                    if (!\$this->canManagePayroll())" => "case 'sync_leave':\n                    if (!\$this->canRunPayroll())",
    "case 'update_components':\n                    if (!\$this->canManagePayroll())" => "case 'update_components':\n                    if (!\$this->canRunPayroll())",
    "case 'save_tenant_settings':\n                    if (!\$this->canManagePayroll())" => "case 'save_tenant_settings':\n                    if (!\$this->canRunPayroll())",
    "case 'delete_schedule':\n                    if (!\$this->canManagePayroll())" => "case 'delete_schedule':\n                    if (!\$this->canRunPayroll())",
    "case 'payslips':\n                    if (!\$this->canManagePayroll())" => "case 'payslips':\n                    if (!\$this->canViewPayroll())",
    "case 'tenant_settings':\n                    if (!\$this->canManagePayroll())" => "case 'tenant_settings':\n                    if (!\$this->canViewPayroll())",
    "\$isPayrollManager = \$this->canManagePayroll();" => "\$isPayrollManager = \$this->canViewPayroll();"
];

foreach ($replacements as $search => $replace) {
    $content = str_replace($search, $replace, $content);
}

// Fallback regex for any leftover canManagePayroll calls inside those case blocks
$content = preg_replace("/case 'finalize_run':(.*?)canManagePayroll/s", "case 'finalize_run':$1canApprovePayroll", $content);
$content = preg_replace("/case 'sync_leave':(.*?)canManagePayroll/s", "case 'sync_leave':$1canRunPayroll", $content);
$content = preg_replace("/case 'update_components':(.*?)canManagePayroll/s", "case 'update_components':$1canRunPayroll", $content);
$content = preg_replace("/case 'save_tenant_settings':(.*?)canManagePayroll/s", "case 'save_tenant_settings':$1canRunPayroll", $content);
$content = preg_replace("/case 'delete_schedule':(.*?)canManagePayroll/s", "case 'delete_schedule':$1canRunPayroll", $content);
$content = preg_replace("/case 'payslips':(.*?)canManagePayroll/s", "case 'payslips':$1canViewPayroll", $content);
$content = preg_replace("/case 'tenant_settings':(.*?)canManagePayroll/s", "case 'tenant_settings':$1canViewPayroll", $content);

file_put_contents($file, $content);
echo "Updated PayrollController.\n";
