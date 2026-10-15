param(
    [string]$OutputFile = ("votacao-" + (Get-Date -Format "yyyyMMdd-HHmmss") + ".dump"),
    [string]$PgBin = $env:PGBIN
)
$ErrorActionPreference = "Stop"
Push-Location $PSScriptRoot
$previousPassword = $env:PGPASSWORD
try {
    $database = (& php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo json_encode(config("database.connections.pgsql"));') | ConvertFrom-Json
    if ($LASTEXITCODE -ne 0) { throw "Não foi possível carregar a configuração do Laravel." }
    $dump = if ($PgBin) { Join-Path $PgBin "pg_dump.exe" } else { "pg_dump" }
    $env:PGPASSWORD = $database.password
    & $dump --no-password --host=$($database.host) --port=$($database.port) --username=$($database.username) --dbname=$($database.database) --format=custom --no-owner --no-acl --file=$OutputFile
    if ($LASTEXITCODE -ne 0) { throw "Falha ao gerar o backup PostgreSQL." }
    if (Test-Path -LiteralPath "storage/app/public") {
        Compress-Archive -Path "storage/app/public/*" -DestinationPath ($OutputFile + ".photos.zip") -Force
    }
    Write-Host "Backup gerado: $OutputFile"
} finally {
    $env:PGPASSWORD = $previousPassword
    Pop-Location
}
