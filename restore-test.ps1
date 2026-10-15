param(
    [Parameter(Mandatory = $true)][string]$BackupFile,
    [string]$PgBin = $env:PGBIN
)
$ErrorActionPreference = "Stop"
Push-Location $PSScriptRoot
$previousPassword = $env:PGPASSWORD
try {
    $database = (& php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo json_encode(config("database.connections.pgsql"));') | ConvertFrom-Json
    if ($LASTEXITCODE -ne 0) { throw "Não foi possível carregar a configuração do Laravel." }
    $restore = if ($PgBin) { Join-Path $PgBin "pg_restore.exe" } else { "pg_restore" }
    $psql = if ($PgBin) { Join-Path $PgBin "psql.exe" } else { "psql" }
    $env:PGPASSWORD = $database.password
    $target = "votacao_restore_test"
    $arguments = @("--no-password", "--host=$($database.host)", "--port=$($database.port)", "--username=$($database.username)")
    & $restore @arguments --dbname=$target --no-owner --no-privileges --single-transaction --exit-on-error $BackupFile
    if ($LASTEXITCODE -ne 0) { throw "Restauração falhou. Use somente o banco separado votacao_restore_test, vazio." }
    $query = @'
SELECT 'votes', count(*), md5(COALESCE(string_agg(row_to_json(v)::text, E'\n' ORDER BY id), '')) FROM votes v
UNION ALL
SELECT 'participations', count(*), md5(COALESCE(string_agg(row_to_json(p)::text, E'\n' ORDER BY round_id, user_id), '')) FROM participations p
UNION ALL
SELECT 'audit_logs', count(*), md5(COALESCE(string_agg(row_to_json(a)::text, E'\n' ORDER BY id), '')) FROM audit_logs a;
'@
    $source = (& $psql @arguments --dbname=$($database.database) --tuples-only --no-align --command=$query) -join "`n"
    if ($LASTEXITCODE -ne 0) { throw "Falha ao ler a origem." }
    $restored = (& $psql @arguments --dbname=$target --tuples-only --no-align --command=$query) -join "`n"
    if ($LASTEXITCODE -ne 0) { throw "Falha ao ler o banco restaurado." }
    if ($source -ne $restored) { throw "Os votos, participações ou logs diferem da origem. Não faça novas operações durante a demonstração do backup." }
    Write-Host "Integridade confirmada: contagem e hash de votos, participações e logs iguais à origem."
    Write-Host $restored
} finally {
    $env:PGPASSWORD = $previousPassword
    Pop-Location
}
