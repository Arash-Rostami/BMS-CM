$configPath = "$env:USERPROFILE\.claude\settings.json"
if (-not (Test-Path (Split-Path $configPath))) { New-Item -ItemType Directory -Path (Split-Path $configPath) -Force | Out-Null }
$config = if (Test-Path $configPath) { Get-Content $configPath -Raw | ConvertFrom-Json -ErrorAction SilentlyContinue } else { New-Object PSObject }
if (-not $config) { $config = New-Object PSObject }
if (-not (Get-Member -InputObject $config -Name "hasCompletedOnboarding")) { Add-Member -InputObject $config -NotePropertyName "hasCompletedOnboarding" -NotePropertyValue $true -Force } else { $config.hasCompletedOnboarding = $true }
$config | ConvertTo-Json | Set-Content $configPath -Force

Remove-Item env:HTTP_PROXY, env:HTTPS_PROXY, env:http_proxy, env:https_proxy, env:ALL_PROXY, env:all_proxy -ErrorAction SilentlyContinue

Stop-Process -Name "ollama" -Force -ErrorAction SilentlyContinue
Start-Process -FilePath "ollama" -ArgumentList "serve" -WindowStyle Hidden
Start-Sleep -Seconds 2

$items = ollama list | Select-Object -Skip 1 | ForEach-Object { ($_ -split '\s+')[0] } | Where-Object { $_ }
if (-not $items) { return }

Write-Host "=== Select Local Model ===" -ForegroundColor Cyan
for ($i = 0; $i -lt $items.Count; $i++) { Write-Host " [$i] $($items[$i])" }
$selection = Read-Host "Enter index"

if ($selection -match '^\d+$' -and [int]$selection -lt $items.Count) {
    $target = $items[[int]$selection]

    $env:ANTHROPIC_AUTH_TOKEN="ollama"
    $env:ANTHROPIC_API_KEY=""
    $env:ANTHROPIC_BASE_URL="http://127.0.0.1:11434"
    $env:CLAUDE_CODE_DISABLE_NONESSENTIAL_TRAFFIC="1"
    $env:NODE_TLS_REJECT_UNAUTHORIZED="0"

    claude --model $target
}
