# Replace every legacy `function __autoload($className) { ezcBase::autoload($className); }`
# block with the modern `spl_autoload_register([...])` equivalent.
#
# `__autoload()` was deprecated in PHP 7.2 and removed in PHP 8.0; even
# though most of these files are documentation samples that never get
# loaded by the production app, leaving them around makes the lint
# noisy. Patch them all in one sweep.
$root = $PSScriptRoot
$pattern = '(?ms)function\s+__autoload\s*\(\s*\$className\s*\)\s*\{\s*ezcBase::autoload\(\s*\$className\s*\);\s*\}'
$replacement = "spl_autoload_register('ezcBase::autoload');"
$count = 0
Get-ChildItem "$root\protected\vendors\ezcomponents" -Recurse -Filter "*.php" -File `
    | ForEach-Object {
        $text = [System.IO.File]::ReadAllText($_.FullName)
        if ($text -match $pattern) {
            $patched = [regex]::Replace($text, $pattern, $replacement)
            [System.IO.File]::WriteAllText($_.FullName, $patched)
            Write-Output "  patched: $($_.FullName.Substring($root.Length+1))"
            $count++
        }
    }
Write-Output "patched $count files"
