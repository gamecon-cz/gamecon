@ECHO OFF
setlocal DISABLEDELAYEDEXPANSION
SET BIN_TARGET=%~dp0/../dg/ftp-deployment/deployment
php "%BIN_TARGET%" %*
