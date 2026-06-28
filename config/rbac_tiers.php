<?php

return [
    'roles' => [
        'Account Owner'   => ['desc' => 'Ultimate tenant owner. Full access.', 'perms' => ['*']],
        'Admin'           => ['desc' => 'Company-wide administrative access', 'perms' => ['users.manage','settings.manage','audit.view','employees.view','employees.edit']],
        'Manager'         => ['desc' => 'Manages their team and approvals', 'perms' => ['employees.view_team','leave.approve_team','performance.manage_team']],
        'Employee'        => ['desc' => 'Standard employee self-service', 'perms' => ['employees.view_self','leave.view','leave.request','attendance.view']],
        'HR Manager'      => ['desc' => 'Manages all employee records and ELR', 'perms' => ['employees.manage','leave.manage','elr.view','elr.investigate','ats.view','ats.edit']],
        'Recruiter'       => ['desc' => 'Manages jobs and candidates', 'perms' => ['ats.view','ats.create_job','ats.edit_job']],
        'Payroll Manager' => ['desc' => 'Runs payroll and manages benefits', 'perms' => ['payroll.view','payroll.run','benefits.manage']],
        'Payroll Approver'=> ['desc' => 'Approves payroll runs', 'perms' => ['payroll.view','payroll.approve','benefits.approve']],
    ],
    'tiers' => [
        'Solo'       => ['roles' => ['Account Owner'], 'default_scope' => 'tenant', 'org_units' => false, 'custom_roles' => false],
        'Small'      => ['roles' => ['Account Owner','Admin','Manager','Employee'], 'default_scope' => 'tenant', 'org_units' => false, 'custom_roles' => false],
        'Mid'        => ['roles' => ['Account Owner','Admin','Manager','Employee','HR Manager','Recruiter','Payroll Manager','Payroll Approver'], 'default_scope' => 'department', 'org_units' => true, 'custom_roles' => true],
        'Enterprise' => ['roles' => ['Account Owner','Admin','Manager','Employee','HR Manager','Recruiter','Payroll Manager','Payroll Approver'], 'default_scope' => 'department', 'org_units' => true, 'custom_roles' => true],
    ],
];
