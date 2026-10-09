param([int]$Port = 8000, [string]$PostgresBin = 'C:\Program Files\PostgreSQL\12\bin')
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $projectRoot
$phpPath = Join-Path $projectRoot '.runtime\php\php.exe'
if (-not (Test-Path -LiteralPath $phpPath)) { throw 'No se encontró PHP portátil en .runtime/php. Usa PHP 8.3+ según README.md o Docker.' }
if (-not (Test-Path -LiteralPath '.env')) { throw 'Configura .env antes de iniciar. Consulta README.md.' }
$dataPath = Join-Path $projectRoot '.runtime\pgdata'
if (Test-Path -LiteralPath (Join-Path $dataPath 'PG_VERSION')) {
    & (Join-Path $PostgresBin 'pg_ctl.exe') -D $dataPath status 2>$null
    if ($LASTEXITCODE -ne 0) {
        $argsPg = @('-D', ('"' + $dataPath + '"'), '-l', ('"' + (Join-Path $projectRoot '.runtime\postgres.log') + '"'), 'start')
        $starter = Start-Process -FilePath (Join-Path $PostgresBin 'pg_ctl.exe') -ArgumentList $argsPg -WindowStyle Hidden -PassThru
        if (-not $starter.WaitForExit(15000)) { throw 'PostgreSQL aún no respondió. Revisa .runtime/postgres.log.' }
        if ($starter.ExitCode -ne 0) { throw 'No se pudo iniciar PostgreSQL.' }
    }
}
& $phpPath artisan migrate --force
if ($LASTEXITCODE -ne 0) { throw 'No se pudo preparar la base de datos.' }
$listener = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue
if ($listener) { Write-Output "El puerto $Port ya está ocupado. Si es SubastaYA, visita http://localhost:$Port"; exit 0 }
$server = Start-Process -FilePath $phpPath -ArgumentList @('artisan','serve','--host=127.0.0.1',"--port=$Port") -WorkingDirectory $projectRoot -WindowStyle Hidden -RedirectStandardOutput (Join-Path $projectRoot '.runtime\server.log') -RedirectStandardError (Join-Path $projectRoot '.runtime\server-error.log') -PassThru
$scheduler = Start-Process -FilePath $phpPath -ArgumentList @('artisan','schedule:work') -WorkingDirectory $projectRoot -WindowStyle Hidden -RedirectStandardOutput (Join-Path $projectRoot '.runtime\scheduler.log') -RedirectStandardError (Join-Path $projectRoot '.runtime\scheduler-error.log') -PassThru
@{ server = $server.Id; scheduler = $scheduler.Id; port = $Port } | ConvertTo-Json | Set-Content -LiteralPath '.runtime\processes.json'
Write-Output "SubastaYA: http://localhost:$Port (servidor PID $($server.Id), planificador PID $($scheduler.Id))"
