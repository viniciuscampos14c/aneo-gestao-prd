param(
    [string]$ProjectName = "aneo"
)

$ErrorActionPreference = "Stop"

function Test-TcpPort {
    param(
        [string]$HostName,
        [int]$Port
    )

    try {
        $client = New-Object Net.Sockets.TcpClient
        $async = $client.BeginConnect($HostName, $Port, $null, $null)
        $ok = $async.AsyncWaitHandle.WaitOne(1000, $false)
        if ($ok -and $client.Connected) {
            $client.EndConnect($async) | Out-Null
            $client.Close()
            return $true
        }
        $client.Close()
        return $false
    } catch {
        return $false
    }
}

function Wait-Port {
    param(
        [string]$HostName,
        [int]$Port,
        [int]$TimeoutSeconds = 60
    )

    $deadline = (Get-Date).AddSeconds($TimeoutSeconds)
    while ((Get-Date) -lt $deadline) {
        if (Test-TcpPort -HostName $HostName -Port $Port) {
            return $true
        }
        Start-Sleep -Seconds 1
    }
    return $false
}

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$sourcePublicHtml = Join-Path $scriptDir "public_html"
$sourceSql = Join-Path $scriptDir "database.sql"
$uploadsBackupRoot = $null
$uploadsBackupDir = $null

$xamppRoot = "C:\xampp"
$targetDir = Join-Path $xamppRoot ("htdocs\" + $ProjectName)
$targetConfig = Join-Path $targetDir "config.php"

$apacheStart = Join-Path $xamppRoot "apache_start.bat"
$mysqlStart = Join-Path $xamppRoot "mysql_start.bat"
$mysqlExe = Join-Path $xamppRoot "mysql\bin\mysql.exe"

if (!(Test-Path $sourcePublicHtml)) {
    # Compatibilidade com estrutura sem subpasta public_html (codigo na raiz do projeto).
    $sourcePublicHtml = $scriptDir
}

if (!(Test-Path $sourcePublicHtml)) {
    throw "Pasta de origem nao encontrada: $sourcePublicHtml"
}

if (!(Test-Path $sourceSql)) {
    throw "Arquivo SQL nao encontrado: $sourceSql"
}

if (!(Test-Path $xamppRoot)) {
    throw "XAMPP nao encontrado em $xamppRoot"
}

Write-Host "[1/6] Iniciando Apache..." -ForegroundColor Cyan
& $apacheStart | Out-Null

Write-Host "[2/6] Iniciando MySQL..." -ForegroundColor Cyan
& $mysqlStart | Out-Null

if (!(Wait-Port -HostName "127.0.0.1" -Port 3306 -TimeoutSeconds 90)) {
    throw "MySQL nao respondeu na porta 3306."
}

if (!(Wait-Port -HostName "127.0.0.1" -Port 80 -TimeoutSeconds 90)) {
    Write-Host "Aviso: Apache nao respondeu na porta 80. Verifique se existe conflito de porta." -ForegroundColor Yellow
}

Write-Host "[3/6] Copiando projeto para htdocs..." -ForegroundColor Cyan
$sourceResolved = (Resolve-Path $sourcePublicHtml).Path.TrimEnd('\')
$targetResolved = $targetDir.TrimEnd('\')
if ($sourceResolved -ieq $targetResolved) {
    throw "Origem e destino sao o mesmo diretorio ($sourceResolved). Rode este script a partir da pasta de desenvolvimento do projeto."
}

if (Test-Path $targetDir) {
    $existingUploadsDir = Join-Path $targetDir "uploads"
    if (Test-Path $existingUploadsDir) {
        $uploadsBackupRoot = Join-Path ([System.IO.Path]::GetTempPath()) ("aneo_uploads_" + [Guid]::NewGuid().ToString("N"))
        $uploadsBackupDir = Join-Path $uploadsBackupRoot "uploads"
        New-Item -ItemType Directory -Path $uploadsBackupRoot -Force | Out-Null
        Copy-Item -Path $existingUploadsDir -Destination $uploadsBackupRoot -Recurse -Force
    }

    Remove-Item $targetDir -Recurse -Force
}
New-Item -ItemType Directory -Path $targetDir -Force | Out-Null
Copy-Item (Join-Path $sourcePublicHtml "*") $targetDir -Recurse -Force

if ($uploadsBackupDir -and (Test-Path $uploadsBackupDir)) {
    Write-Host "      Restaurando uploads existentes..." -ForegroundColor DarkCyan
    $targetUploadsDir = Join-Path $targetDir "uploads"
    if (!(Test-Path $targetUploadsDir)) {
        New-Item -ItemType Directory -Path $targetUploadsDir -Force | Out-Null
    }

    Get-ChildItem -Path $uploadsBackupDir -Force | ForEach-Object {
        Copy-Item -Path $_.FullName -Destination $targetUploadsDir -Recurse -Force
    }
}

if ($uploadsBackupRoot -and (Test-Path $uploadsBackupRoot)) {
    Remove-Item $uploadsBackupRoot -Recurse -Force
}

Write-Host "[4/6] Ajustando config.php para ambiente local..." -ForegroundColor Cyan
$config = Get-Content $targetConfig -Raw
$config = $config -replace "'host'\s*=>\s*'[^']*'", "'host' => 'localhost'"
$config = $config -replace "'port'\s*=>\s*\d+", "'port' => 3306"
$config = $config -replace "'name'\s*=>\s*'[^']*'", "'name' => 'aneo_gestao'"
$config = $config -replace "'user'\s*=>\s*'[^']*'", "'user' => 'root'"
$config = $config -replace "'pass'\s*=>\s*'[^']*'", "'pass' => ''"
Set-Content -Path $targetConfig -Value $config -Encoding UTF8

Write-Host "[5/6] Importando banco de dados..." -ForegroundColor Cyan
$sqlText = Get-Content $sourceSql -Raw
$sqlText | & $mysqlExe -u root

Write-Host "[6/6] Finalizado." -ForegroundColor Green
Write-Host ""
Write-Host "Abra no navegador:" -ForegroundColor Green
Write-Host ("http://localhost/{0}/index.php?route=login" -f $ProjectName) -ForegroundColor White
Write-Host ""
Write-Host "Login inicial:" -ForegroundColor Green
Write-Host "usuario: admin" -ForegroundColor White
Write-Host "senha:   admin123" -ForegroundColor White
