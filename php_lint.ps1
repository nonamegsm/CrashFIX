# Lint every .php file under protected/ (skipping framework/ and
# framework_old/) using Windows-side PHP.
$root = $PSScriptRoot
$php  = "C:\xampp\php\php.exe"

$skipPattern = '\\(framework|framework_old)\\|\\vendors\\.+\\tests\\'
$errors = 0
$count  = 0

Get-ChildItem "$root\protected" -Recurse -Filter "*.php" -File `
    | Where-Object { $_.FullName -notmatch $skipPattern } `
    | ForEach-Object {
        $count++
        $out = & $php -l $_.FullName 2>&1
        if ($LASTEXITCODE -ne 0) {
            $errors++
            Write-Output "FAIL: $($_.FullName.Substring($root.Length+1))"
            $out -split "`n" | ForEach-Object {
                # drop the Imagick version-mismatch warning - it's
                # CLI-only noise and unrelated to the file under test
                if ($_ -notmatch 'Imagick' -and $_.Trim()) {
                    Write-Output "    $_"
                }
            }
        }
    }

Write-Output ""
Write-Output "scanned: $count files, errors: $errors"
exit $errors
