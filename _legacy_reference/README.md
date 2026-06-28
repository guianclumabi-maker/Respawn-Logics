# Legacy page reference (recovered from git)

These 9 PHP pages were the working UIs for these modules BEFORE commit
`b509030` ("phase 5 - unify SPA... delete all legacy php files", 2026-06-20)
deleted them and replaced them with empty "Migration Pending" React stubs.

They are recovered here ONLY as a blueprint for re-implementing each module
in React (frontend/src/app/pages/). They are NOT wired into the app and should
NOT be served. Each file shows what data the screen displayed and which API
endpoints / controller actions it called — use that as the porting spec.

Backend controllers still exist and back these modules:
  analytics       -> backend/controllers/AnalyticsController.php
  attendance      -> backend/controllers/AttendanceController.php
  benefits_admin  -> backend/controllers/BenefitsController.php
  compensation_*  -> (compensation logic) 
  expenses_admin  -> backend/controllers/ExpensesController.php
  performance_*   -> backend/controllers/PerformanceController.php
  scheduling      -> backend/controllers/ShiftController.php
  surveys         -> backend/controllers/SurveyController.php
