# PHP 8.2 compatibility patcher for the legacy CrashFix Yii1 codebase.
#
# Adds `#[\AllowDynamicProperties]` immediately above every class
# declaration in the application's models / components / controllers
# / commands. Yii1's CComponent / CActiveRecord / CController machinery
# relies heavily on dynamic property assignment (e.g. $model->myAttr
# routed through CComponent::__set), which PHP 8.2 deprecates with a
# warning that becomes a hard error under strict error handling.
#
# The attribute is the officially-blessed opt-out; it makes the class
# behave the same on PHP 8.0/8.1/8.2/8.3/8.4.
#
# Run from the repo root:
#     pwsh ./php8_patch.ps1
#
# Idempotent: skips files that already carry the attribute.

$root = $PSScriptRoot
$dirs = @(
    "$root\protected\models",
    "$root\protected\controllers",
    "$root\protected\components",
    "$root\protected\commands",
    "$root\protected\extensions"
)

$pattern  = '(?ms)(^|\r?\n)(?<lead>(?:abstract\s+|final\s+)?class\s+\w+)'
$attrLine = '#[\AllowDynamicProperties]'

$total = 0
$patched = 0

foreach ($d in $dirs) {
    if (-not (Test-Path $d)) { continue }
    foreach ($f in Get-ChildItem $d -Filter *.php -File -Recurse -ErrorAction SilentlyContinue) {
        $total++
        $text = [System.IO.File]::ReadAllText($f.FullName)
        if ($text -match [regex]::Escape($attrLine)) {
            continue   # already patched
        }
        if ($text -notmatch $pattern) {
            continue   # not a class file (form helpers, plain functions, etc.)
        }
        $patchedText = [regex]::Replace($text, $pattern, "`$1$attrLine`r`n`$2")
        [System.IO.File]::WriteAllText($f.FullName, $patchedText)
        $patched++
        Write-Output "  patched: $($f.FullName.Substring($root.Length+1))"
    }
}

Write-Output ""
Write-Output "Scanned $total PHP files, patched $patched."
