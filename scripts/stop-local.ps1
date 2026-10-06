param([switch]$StopDatabase, [string]$PostgresBin = 'C:\Program Files\PostgreSQL\12\bin')
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$processFile = Join-Path $projectRoot '.runtime\processes.json'
if (Test-Path -LiteralPath $processFile) {
    $pids = Get-Content -LiteralPath $processFile | ConvertFrom-Json
    $expectedPhp = Join-Path $projectRoot '.runtime\php\php.exe'
    foreach ($taskId in @($pids.server, $pids.scheduler)) {
        $process = Get-CimInstance Win32_Process -Filter "ProcessId=$taskId" -ErrorAction SilentlyContinue
        # No detener un PID reutilizado por otro programa.
        if ($process -and $process.ExecutablePath -eq $expectedPhp -and $process.CommandLine -match 'artisan (serve|schedule:work)') {
            & taskkill.exe /PID $taskId /T /F | Out-Null
        }
    }
    Remove-Item -LiteralPath $processFile
}
if ($StopDatabase) {
    $dataPath = Join-Path $projectRoot '.runtime\pgdata'
    if (Test-Path -LiteralPath (Join-Path $dataPath 'PG_VERSION')) {
        & (Join-Path $PostgresBin 'pg_ctl.exe') -D $dataPath -m fast stop
    }
}
Write-Output 'Procesos locales de SubastaYA detenidos. Los datos se conservan.'
