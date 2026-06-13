# setup_weekly_task.ps1
# This script creates a Windows Scheduled Task to run the Labor Law Fetcher every Friday.

$TaskName = "RespawnLogics_LaborLawFetcher"
$PhpExecutable = "C:\xampp\php\php.exe"
$ScriptPath = "C:\xampp\htdocs\respawn-logics\fetch_labor_laws.php"

# Check if task already exists and delete if it does to avoid conflicts
$taskExists = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
if ($taskExists) {
    Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false
    Write-Host "Removed existing task: $TaskName"
}

# Create a new trigger: Weekly on Fridays at 8:00 AM
$Trigger = New-ScheduledTaskTrigger -Weekly -DaysOfWeek Friday -At 8:00AM

# Create an action to execute the PHP script
$Action = New-ScheduledTaskAction -Execute $PhpExecutable -Argument $ScriptPath -WorkingDirectory "C:\xampp\htdocs\respawn-logics"

# Create settings
$Settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -RunOnlyIfNetworkAvailable

# Register the Scheduled Task
Register-ScheduledTask -TaskName $TaskName -Trigger $Trigger -Action $Action -Settings $Settings -Description "Fetches official labor advisories into the HR Knowledge Base every Friday." -User "SYSTEM"

Write-Host "Success! The Labor Law Fetcher has been scheduled to run every Friday at 8:00 AM."
Write-Host "Task Name: $TaskName"
