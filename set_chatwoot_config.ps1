param(
    [string]$ConfigPath = "config.php",
    [string]$TemplatePath = "chatwoot_config.json",
    [string]$Enabled,
    [string]$BaseUrl,
    [string]$AccountId,
    [string]$InboxId,
    [string]$ApiAccessToken,
    [string]$WebhookToken,
    [switch]$Interactive,
    [switch]$AlsoUpdateXamppCopy,
    [string]$XamppProjectName = "aneo"
)

$ErrorActionPreference = "Stop"

function Escape-PhpSingleQuoted {
    param([string]$Value)
    $escaped = ($Value -replace '\\', '\\\\')
    $escaped = ($escaped -replace "'", "\\'")
    return $escaped
}

function Resolve-FromScriptDir {
    param([string]$Path)
    if ([System.IO.Path]::IsPathRooted($Path)) {
        return $Path
    }
    return (Join-Path $scriptDir $Path)
}

function Read-RequiredInput {
    param(
        [string]$Prompt,
        [string]$CurrentValue
    )

    if ($CurrentValue -and $CurrentValue.Trim() -ne "") {
        return $CurrentValue.Trim()
    }

    while ($true) {
        $value = Read-Host $Prompt
        if ($value -and $value.Trim() -ne "") {
            return $value.Trim()
        }
        Write-Host "Valor obrigatorio." -ForegroundColor Yellow
    }
}

function Set-PhpKeyValue {
    param(
        [string]$InnerBlock,
        [string]$Key,
        [string]$ValueLiteral
    )

    $linePattern = "(?m)^(\s*'" + [regex]::Escape($Key) + "'\s*=>\s*)(.*?)(,\s*)$"

    if ([regex]::IsMatch($InnerBlock, $linePattern)) {
        return [regex]::Replace(
            $InnerBlock,
            $linePattern,
            {
                param($m)
                return $m.Groups[1].Value + $ValueLiteral + $m.Groups[3].Value
            },
            1
        )
    }

    $appendLine = "`r`n        '$Key' => $ValueLiteral,"
    return ($InnerBlock.TrimEnd() + $appendLine + "`r`n    ")
}

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$resolvedConfigPath = Resolve-FromScriptDir -Path $ConfigPath
$resolvedTemplatePath = Resolve-FromScriptDir -Path $TemplatePath

if (!(Test-Path $resolvedConfigPath)) {
    throw "Arquivo de config nao encontrado: $resolvedConfigPath"
}

$templateData = $null
if (Test-Path $resolvedTemplatePath) {
    try {
        $templateData = Get-Content $resolvedTemplatePath -Raw | ConvertFrom-Json
    } catch {
        throw "Falha ao ler JSON de template: $resolvedTemplatePath"
    }
}

if (!$Enabled -and $templateData -and $null -ne $templateData.enabled) {
    $Enabled = [string]$templateData.enabled
}
if (!$BaseUrl -and $templateData -and $templateData.base_url) {
    $BaseUrl = [string]$templateData.base_url
}
if (!$AccountId -and $templateData -and $templateData.account_id) {
    $AccountId = [string]$templateData.account_id
}
if (!$InboxId -and $templateData -and $templateData.inbox_id) {
    $InboxId = [string]$templateData.inbox_id
}
if (!$ApiAccessToken -and $templateData -and $templateData.api_access_token) {
    $ApiAccessToken = [string]$templateData.api_access_token
}
if (!$WebhookToken -and $templateData -and $templateData.webhook_token) {
    $WebhookToken = [string]$templateData.webhook_token
}

if ($Interactive) {
    $Enabled = Read-RequiredInput -Prompt "chatwoot.enabled (true/false)" -CurrentValue $Enabled
    $BaseUrl = Read-RequiredInput -Prompt "chatwoot.base_url" -CurrentValue $BaseUrl
    $AccountId = Read-RequiredInput -Prompt "chatwoot.account_id" -CurrentValue $AccountId
    $InboxId = Read-RequiredInput -Prompt "chatwoot.inbox_id" -CurrentValue $InboxId
    $ApiAccessToken = Read-RequiredInput -Prompt "chatwoot.api_access_token" -CurrentValue $ApiAccessToken
    $WebhookToken = Read-RequiredInput -Prompt "chatwoot.webhook_token" -CurrentValue $WebhookToken
}

$Enabled = Read-RequiredInput -Prompt "chatwoot.enabled (true/false)" -CurrentValue $Enabled
$BaseUrl = Read-RequiredInput -Prompt "chatwoot.base_url" -CurrentValue $BaseUrl
$AccountId = Read-RequiredInput -Prompt "chatwoot.account_id" -CurrentValue $AccountId
$InboxId = Read-RequiredInput -Prompt "chatwoot.inbox_id" -CurrentValue $InboxId
$ApiAccessToken = Read-RequiredInput -Prompt "chatwoot.api_access_token" -CurrentValue $ApiAccessToken
$WebhookToken = Read-RequiredInput -Prompt "chatwoot.webhook_token" -CurrentValue $WebhookToken

$enabledNormalized = $Enabled.Trim().ToLowerInvariant()
if ($enabledNormalized -notin @("true", "false")) {
    throw "Valor invalido para enabled: $Enabled. Use true ou false."
}
$enabledLiteral = if ($enabledNormalized -eq "true") { "true" } else { "false" }

$baseUrlEsc = Escape-PhpSingleQuoted $BaseUrl
$accountEsc = Escape-PhpSingleQuoted $AccountId
$inboxEsc = Escape-PhpSingleQuoted $InboxId
$tokenEsc = Escape-PhpSingleQuoted $ApiAccessToken
$webhookTokenEsc = Escape-PhpSingleQuoted $WebhookToken

$content = Get-Content $resolvedConfigPath -Raw
$blockPattern = "(?s)('chatwoot'\s*=>\s*\[)(.*?)(\s*\],)"

if ($content -notmatch $blockPattern) {
    throw "Bloco 'chatwoot' nao encontrado em $resolvedConfigPath"
}

$match = [regex]::Match($content, $blockPattern)
$inner = $match.Groups[2].Value

$inner = Set-PhpKeyValue -InnerBlock $inner -Key "enabled" -ValueLiteral $enabledLiteral
$inner = Set-PhpKeyValue -InnerBlock $inner -Key "base_url" -ValueLiteral "'$baseUrlEsc'"
$inner = Set-PhpKeyValue -InnerBlock $inner -Key "account_id" -ValueLiteral "'$accountEsc'"
$inner = Set-PhpKeyValue -InnerBlock $inner -Key "inbox_id" -ValueLiteral "'$inboxEsc'"
$inner = Set-PhpKeyValue -InnerBlock $inner -Key "api_access_token" -ValueLiteral "'$tokenEsc'"
$inner = Set-PhpKeyValue -InnerBlock $inner -Key "webhook_token" -ValueLiteral "'$webhookTokenEsc'"

$newBlock = $match.Groups[1].Value + $inner + $match.Groups[3].Value
$updated = $content.Remove($match.Index, $match.Length).Insert($match.Index, $newBlock)

$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$backupPath = "$resolvedConfigPath.bak.$timestamp"
Copy-Item -Path $resolvedConfigPath -Destination $backupPath -Force

Set-Content -Path $resolvedConfigPath -Value $updated -Encoding UTF8

Write-Host "Config Chatwoot atualizada com sucesso." -ForegroundColor Green
Write-Host "Arquivo: $resolvedConfigPath" -ForegroundColor White
Write-Host "Backup:  $backupPath" -ForegroundColor White
Write-Host ""
Write-Host "Resumo aplicado:" -ForegroundColor Cyan
Write-Host "enabled: $enabledLiteral"
Write-Host "base_url: $BaseUrl"
Write-Host "account_id: $AccountId"
Write-Host "inbox_id: $InboxId"
Write-Host "api_access_token: ********"
Write-Host "webhook_token: ********"

if ($AlsoUpdateXamppCopy) {
    $xamppConfig = "C:\xampp\htdocs\$XamppProjectName\config.php"
    if (Test-Path $xamppConfig) {
        Copy-Item -Path $resolvedConfigPath -Destination $xamppConfig -Force
        Write-Host ""
        Write-Host "Copia do XAMPP atualizada: $xamppConfig" -ForegroundColor Green
    } else {
        Write-Host ""
        Write-Host "Aviso: nao encontrei config do XAMPP em $xamppConfig" -ForegroundColor Yellow
    }
}
