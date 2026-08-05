# İncaksesuar Auto-Builder and Packager
# Generates a timestamped deployment ZIP and stamps the last update time on the website footer.

# 1. Get current date/time formatted
$dateStr = Get-Date -Format "dd.MM.yyyy HH:mm"
$zipDateStr = Get-Date -Format "yyyyMMdd_HHmm"

# 2. Overwrite config/version.php
$versionContent = @"
<?php
define('LAST_UPDATE', '$dateStr');
"@

Set-Content -Path "config/version.php" -Value $versionContent -Encoding UTF8
Write-Host "Stamped LAST_UPDATE: $dateStr inside config/version.php"

# 3. Define deployment ZIP name
$zipName = "incaksesuar_deploy_$zipDateStr.zip"

Write-Host "Packaging deployment archive: $zipName..."

# 4. Create ZIP package
Compress-Archive -Path admin, api, assets, config, public, uploads, .htaccess, build.ps1 -DestinationPath $zipName -Force

Write-Host "Deploy package created successfully: $zipName"
