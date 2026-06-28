<?php
$config = require 'config/permissions.php';
$keys = [];
foreach($config as $group => $items) {
    foreach($items as $i) {
        $keys[] = $i;
    }
}
$controllerKeys = ['analytics.view','announcements.manage','ats.create_job','ats.delete','ats.edit','ats.edit_job','ats.view','attendance.manage','audit.view','benefits.manage','elr.close','elr.investigate','elr.view','employees.manage','employees.view','esm.manage','expenses.manage','intelligence.view','leave.manage','leave.request','payroll.approve','payroll.manage','payroll.run','payroll.view','performance.manage','performance.manage_team','settings.manage','shifts.manage','surveys.manage','users.manage'];
$diff = array_diff($controllerKeys, $keys);
echo empty($diff) ? 'EMPTY' : 'DIFF: ' . implode(', ', $diff);
