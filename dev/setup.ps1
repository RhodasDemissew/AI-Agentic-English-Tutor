$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)
if (!(Test-Path '.local/moodle/version.php')) {
    git clone --depth 1 --branch v4.4.2 https://github.com/moodle/moodle.git .local/moodle
    if ($LASTEXITCODE) { throw 'Moodle download failed' }
}
if (!(Test-Path '.local/moodle/config.php')) { Copy-Item dev/config.php .local/moodle/config.php }
docker compose up -d --build web
if ($LASTEXITCODE) { throw 'Docker startup failed' }
docker compose exec -T web chown www-data:www-data /var/moodledata
docker compose exec -T --user www-data web php admin/cli/install_database.php --agree-license --fullname='AI English Lessons Development' --shortname='AIEL Dev' --adminuser=admin --adminpass='LocalDev-Only-42!' --adminemail=admin@example.invalid
if ($LASTEXITCODE) { throw 'Installation failed or database already installed. For an existing installation run admin/cli/upgrade.php --non-interactive.' }
docker compose up -d cron
Write-Host 'Local site: http://localhost:8088 ; admin / LocalDev-Only-42! (local development only)'
