<?php

return [
    'Users' => [
        'users.view',
        'users.create',
        'users.edit',
        'users.delete',
        'users.manage'
    ],
    'Employees' => [
        'employees.view',
        'employees.create',
        'employees.edit',
        'employees.delete',
        'employees.view_self',
        'employees.view_team'
    ],
    'Leave' => [
        'leave.view',
        'leave.request',
        'leave.approve',
        'leave.approve_team'
    ],
    'Attendance' => [
        'attendance.view',
        'attendance.manage',
        'shifts.manage'
    ],
    'Compensation' => [
        'payroll.manage',
        'benefits.manage',
        'expenses.manage',
        'compensation.manage'
    ],
    'Performance' => [
        'performance.manage',
        'performance.manage_team'
    ],
    'Engagement' => [
        'surveys.manage',
        'announcements.manage'
    ],
    'ATS' => [
        'ats.view',
        'ats.create_job',
        'ats.edit_job',
        'ats.edit',
        'ats.delete'
    ],
    'ELR' => [
        'elr.view',
        'elr.investigate',
        'elr.close'
    ],
    'ESM' => [
        'esm.manage',
        'assets.manage'
    ],
    'Analytics' => [
        'analytics.view',
        'intelligence.view'
    ],
    'Settings' => [
        'settings.manage',
        'audit.view'
    ]
];
