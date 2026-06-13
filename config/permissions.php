<?php

return [
    'Users' => [
        'users.view',
        'users.create',
        'users.edit',
        'users.delete'
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
        'attendance.manage'
    ],
    'ATS' => [
        'ats.view',
        'ats.create_job',
        'ats.edit_job'
    ],
    'ELR' => [
        'elr.view',
        'elr.investigate',
        'elr.close'
    ],
    'Analytics' => [
        'analytics.view'
    ],
    'Settings' => [
        'settings.manage'
    ]
];
