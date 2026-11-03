param(
    [ValidateRange(1, 60)][int]$Intervalo = 5,
    [ValidateRange(0, 120)][int]$Preparacion = 15,
    [ValidateRange(2, 60)][int]$Cierre = 10
)
$ErrorActionPreference = 'Stop'
Push-Location -LiteralPath (Split-Path -Parent $PSScriptRoot)
try {
    & docker compose exec -T laravel php artisan subastas:simular camara "--intervalo=$Intervalo" "--preparacion=$Preparacion" "--cierre=$Cierre"
    if ($LASTEXITCODE -ne 0) { throw 'La simulacion no termino correctamente. Revisa el mensaje anterior.' }
} finally {
    Pop-Location
}
