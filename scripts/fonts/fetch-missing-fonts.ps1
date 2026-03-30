param(
  [string]$FontsDir = (Join-Path $PSScriptRoot "..\..\public\assets\fonts"),
  [string]$DesiredListPath = (Join-Path $PSScriptRoot "desired-fonts.txt"),
  [switch]$WhatIf
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Get-NormalizedName([string]$s) {
  # normalize for fuzzy matching: lower, remove non-alnum
  return ($s.ToLowerInvariant() -replace "[^a-z0-9]", "")
}

function Get-FamilyCandidatesFromFileName([string]$fileNameNoExt) {
  $candidates = New-Object System.Collections.Generic.List[string]

  $base = $fileNameNoExt
  # strip common style suffixes
  $base = $base -replace "(-| )Regular$", ""

  # Primary guess: replace hyphens/underscores with spaces
  $guess1 = ($base -replace "[-_]+", " ").Trim()
  if ($guess1) { $candidates.Add($guess1) }

  # Secondary: insert spaces before capitals/digits when missing
  # NOTE: PowerShell -replace is case-insensitive by default; use -creplace to avoid splitting every pair of letters.
  $guess2 = ($guess1 -creplace "([a-z])([A-Z0-9])", '$1 $2' -creplace "([0-9])([A-Za-z])", '$1 $2').Trim()
  if ($guess2 -and -not $candidates.Contains($guess2)) { $candidates.Add($guess2) }

  # Tertiary: special-case a few known tricky families
  switch ($fileNameNoExt) {
    "BebasNeue-Regular" { $candidates.Add("Bebas Neue") }
    "CormorantGaramond-Regular" { $candidates.Add("Cormorant Garamond") }
    "DancingScript-Regular" { $candidates.Add("Dancing Script") }
    "ElMessiri-Regular" { $candidates.Add("El Messiri") }
    "FjallaOne-Regular" { $candidates.Add("Fjalla One") }
    "GentiumBookPlus-Regular" { $candidates.Add("Gentium Book Plus") }
    "GildaDisplay-Regular" { $candidates.Add("Gilda Display") }
    "HammersmithOne-Regular" { $candidates.Add("Hammersmith One") }
    "IBM Plex Sans" { $candidates.Add("IBM Plex Sans") }
    "JosefinSans-Regular" { $candidates.Add("Josefin Sans") }
    "Newsreader-Regular" { $candidates.Add("Newsreader") }
    "NoticiaText-Regular" { $candidates.Add("Noticia Text") }
    "PlayfairDisplay-Regular" { $candidates.Add("Playfair Display") }
    "PTSans-Regular" { $candidates.Add("PT Sans") }
    "Quattrocento-Regular" { $candidates.Add("Quattrocento") }
    "SpaceGrotesk-Regular" { $candidates.Add("Space Grotesk") }
    "SourceSans3-Regular" { $candidates.Add("Source Sans 3") }
    "TitilliumWeb-Regular" { $candidates.Add("Titillium Web") }
    "VarelaRound-Regular" { $candidates.Add("Varela Round") }
    "WixMadeforText-Regular" { $candidates.Add("Wix Madefor Text") }
    "XanhMono-Regular" { $candidates.Add("Xanh Mono") }
    "YanoneKaffeesatz-Regular" { $candidates.Add("Yanone Kaffeesatz") }
    "YesevaOne-Regular" { $candidates.Add("Yeseva One") }
    "ZCOOLKuaiLe-Regular" { $candidates.Add("ZCOOL KuaiLe") }
    default { }
  }

  # Deduplicate while keeping order
  $seen = @{}
  $out = New-Object System.Collections.Generic.List[string]
  foreach ($c in $candidates) {
    $k = $c.ToLowerInvariant()
    if (-not $seen.ContainsKey($k)) { $seen[$k] = $true; $out.Add($c) }
  }
  return $out
}

function Try-DownloadGoogleFontsZip([string]$family, [string]$destZipPath) {
  $encoded = [uri]::EscapeDataString($family)
  $url = "https://fonts.google.com/download?family=$encoded"
  try {
    $headers = @{
      "User-Agent" = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
      "Accept"     = "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8"
    }
    Invoke-WebRequest -Uri $url -OutFile $destZipPath -Headers $headers -MaximumRedirection 10 | Out-Null
    if ((Get-Item $destZipPath).Length -lt 1024) { return $false }
    return $true
  } catch {
    return $false
  }
}

function Test-IsTtfFile([string]$path) {
  try {
    $fs = [System.IO.File]::OpenRead($path)
    try {
      $buf = New-Object byte[] 4
      $read = $fs.Read($buf, 0, 4)
      if ($read -ne 4) { return $false }
      # TTF: 00 01 00 00, OTF: 'OTTO'
      if ($buf[0] -eq 0x00 -and $buf[1] -eq 0x01 -and $buf[2] -eq 0x00 -and $buf[3] -eq 0x00) { return $true }
      if ($buf[0] -eq 0x4F -and $buf[1] -eq 0x54 -and $buf[2] -eq 0x54 -and $buf[3] -eq 0x4F) { return $true }
      return $false
    } finally {
      $fs.Dispose()
    }
  } catch {
    return $false
  }
}

function Try-DownloadGoogleFontsRawTtf(
  [string]$family,
  [string]$desiredFileName,
  [string]$destTtfPath
) {
  $familyNoSpace = ($family -replace "\s+", "")
  $familySlug = (Get-NormalizedName $family) # lowercase alnum

  $fileCandidates = New-Object System.Collections.Generic.List[string]
  # common patterns in google/fonts repo
  $fileCandidates.Add("$familyNoSpace-Regular.ttf")
  $fileCandidates.Add("$familyNoSpace.ttf")
  # variable fonts (common in newer families)
  $fileCandidates.Add("$familyNoSpace[wght].ttf")
  $fileCandidates.Add("$familyNoSpace-Italic[wght].ttf")
  $fileCandidates.Add("$familyNoSpace[wdth,wght].ttf")
  $fileCandidates.Add("$familyNoSpace-Italic[wdth,wght].ttf")
  $fileCandidates.Add("$familyNoSpace[opsz,wght].ttf")
  $fileCandidates.Add("$familyNoSpace-Italic[opsz,wght].ttf")
  $fileCandidates.Add("$familyNoSpace[ital,opsz,wght].ttf")
  $fileCandidates.Add("$familyNoSpace[opsz,wdth,wght].ttf")
  $fileCandidates.Add("$familyNoSpace-Italic[opsz,wdth,wght].ttf")

  # Some families use different casing; try TitleCase-ish from tokens
  $tokens = ($family -split "\s+") | Where-Object { $_ }
  $title = ($tokens | ForEach-Object { $_.Substring(0,1).ToUpperInvariant() + $_.Substring(1) }) -join ""
  if ($title -and $title -ne $familyNoSpace) {
    $fileCandidates.Add("$title-Regular.ttf")
    $fileCandidates.Add("$title.ttf")
    $fileCandidates.Add("$title[wght].ttf")
    $fileCandidates.Add("$title-Italic[wght].ttf")
    $fileCandidates.Add("$title[wdth,wght].ttf")
    $fileCandidates.Add("$title-Italic[wdth,wght].ttf")
    $fileCandidates.Add("$title[opsz,wght].ttf")
    $fileCandidates.Add("$title-Italic[opsz,wght].ttf")
    $fileCandidates.Add("$title[ital,opsz,wght].ttf")
    $fileCandidates.Add("$title[opsz,wdth,wght].ttf")
    $fileCandidates.Add("$title-Italic[opsz,wdth,wght].ttf")
  }

  # Special-case: PT Sans uses legacy filename in google/fonts
  if ($familySlug -eq "ptsans") {
    $fileCandidates.Add("PT_Sans-Web-Regular.ttf")
  }

  $dirs = @("ofl", "apache", "ufl")
  $subDirs = @("", "static", "ttf", "static/ttf")
  $headers = @{ "User-Agent" = "Mozilla/5.0" }

  foreach ($d in $dirs) {
    foreach ($sd in $subDirs) {
      foreach ($remoteFile in $fileCandidates) {
        $pathPart = if ($sd) { "$sd/" } else { "" }
        $url = "https://raw.githubusercontent.com/google/fonts/main/$d/$familySlug/$pathPart$remoteFile"
        try {
          $tmp = $destTtfPath + ".tmp"
          if (Test-Path $tmp) { Remove-Item $tmp -Force -ErrorAction SilentlyContinue }
          Invoke-WebRequest -Uri $url -OutFile $tmp -Headers $headers -MaximumRedirection 5 | Out-Null
          if ((Test-Path $tmp) -and (Get-Item $tmp).Length -gt 2048 -and (Test-IsTtfFile $tmp)) {
            Move-Item -Path $tmp -Destination $destTtfPath -Force
            return $true
          }
          if (Test-Path $tmp) { Remove-Item $tmp -Force -ErrorAction SilentlyContinue }
        } catch {
          if (Test-Path $tmp) { Remove-Item $tmp -Force -ErrorAction SilentlyContinue }
        }
      }
    }
  }

  return $false
}

function Try-DownloadAlternateTtf([string]$family, [string]$destTtfPath) {
  $slug = (Get-NormalizedName $family)
  $headers = @{ "User-Agent" = "Mozilla/5.0" }

  switch ($slug) {
    "xolonium" {
      $url = "https://raw.githubusercontent.com/dev-ext/font-face/master/%40fontface-other/xolonium/Xolonium-Regular/fonts/Xolonium-Regular.ttf"
      try {
        $tmp = $destTtfPath + ".tmp"
        if (Test-Path $tmp) { Remove-Item $tmp -Force -ErrorAction SilentlyContinue }
        Invoke-WebRequest -Uri $url -OutFile $tmp -Headers $headers -MaximumRedirection 5 | Out-Null
        if ((Test-Path $tmp) -and (Get-Item $tmp).Length -gt 2048 -and (Test-IsTtfFile $tmp)) {
          Move-Item -Path $tmp -Destination $destTtfPath -Force
          return $true
        }
        if (Test-Path $tmp) { Remove-Item $tmp -Force -ErrorAction SilentlyContinue }
        return $false
      } catch {
        if (Test-Path $tmp) { Remove-Item $tmp -Force -ErrorAction SilentlyContinue }
        return $false
      }
    }
    default { return $false }
  }
}

function Find-BestTtfInExtractedFolder([string]$folder, [string]$desiredFileNameNoExt) {
  $desiredNorm = Get-NormalizedName $desiredFileNameNoExt
  $ttfs = Get-ChildItem -Path $folder -Recurse -File -Filter "*.ttf" -ErrorAction SilentlyContinue
  if (-not $ttfs) { return $null }

  # Prefer exact normalized filename match (ignoring spaces/hyphens)
  foreach ($f in $ttfs) {
    $norm = Get-NormalizedName $f.BaseName
    if ($norm -eq $desiredNorm) { return $f.FullName }
  }

  # Next: prefer "Regular" variant if present
  foreach ($f in $ttfs) {
    if ($f.BaseName -match "Regular$") { return $f.FullName }
  }

  # Fallback: first ttf
  return $ttfs[0].FullName
}

if (-not (Test-Path $DesiredListPath)) {
  throw "Desired list not found: $DesiredListPath"
}
if (-not (Test-Path $FontsDir)) {
  New-Item -ItemType Directory -Path $FontsDir | Out-Null
}

$desired = @(Get-Content $DesiredListPath | ForEach-Object { $_.Trim() } | Where-Object { $_ -and -not $_.StartsWith("#") })
$desired = @($desired | Where-Object { $_.ToLowerInvariant().EndsWith(".ttf") })

$existing = @{}
Get-ChildItem -Path $FontsDir -File -Filter "*.ttf" | ForEach-Object { $existing[$_.Name] = $true }

$missing = @($desired | Where-Object { -not $existing.ContainsKey($_) })

Write-Host "FontsDir: $FontsDir"
Write-Host ("Desired: {0} | Existing: {1} | Missing: {2}" -f $desired.Count, $existing.Count, $missing.Count)

if ($missing.Count -eq 0) {
  Write-Host "Nothing to do."
  exit 0
}

$tmpRoot = Join-Path ([System.IO.Path]::GetTempPath()) ("countdownly-fonts-" + [guid]::NewGuid().ToString("n"))
New-Item -ItemType Directory -Path $tmpRoot | Out-Null

$downloaded = 0
$failed = New-Object System.Collections.Generic.List[string]

try {
  foreach ($targetFile in $missing) {
    $targetPath = Join-Path $FontsDir $targetFile
    $nameNoExt = [System.IO.Path]::GetFileNameWithoutExtension($targetFile)

    $candidates = Get-FamilyCandidatesFromFileName $nameNoExt
    $ok = $false

    foreach ($family in $candidates) {
      $zipPath = Join-Path $tmpRoot ((Get-NormalizedName $family) + ".zip")
      $extractDir = Join-Path $tmpRoot ((Get-NormalizedName $family) + "-extracted")

      if (Test-Path $zipPath) { Remove-Item $zipPath -Force -ErrorAction SilentlyContinue }
      if (Test-Path $extractDir) { Remove-Item $extractDir -Recurse -Force -ErrorAction SilentlyContinue }

      Write-Host "-> $targetFile : trying '$family'"
      # First try raw TTF from google/fonts repo (fast, reliable)
      if (-not $WhatIf) {
        if (Try-DownloadGoogleFontsRawTtf -family $family -desiredFileName $targetFile -destTtfPath $targetPath) {
          $downloaded++
          $ok = $true
          break
        }
        # Alternate sources for fonts not in google/fonts
        if (Try-DownloadAlternateTtf -family $family -destTtfPath $targetPath) {
          $downloaded++
          $ok = $true
          break
        }
      } else {
        Write-Host ("   WHATIF would download from google/fonts -> '{0}'" -f $targetPath)
        $downloaded++
        $ok = $true
        break
      }

      # Fallback: try Google Fonts zip download + extract
      if (-not (Try-DownloadGoogleFontsZip -family $family -destZipPath $zipPath)) { continue }
      try { Expand-Archive -Path $zipPath -DestinationPath $extractDir -Force } catch { continue }

      $bestTtf = Find-BestTtfInExtractedFolder -folder $extractDir -desiredFileNameNoExt $nameNoExt
      if (-not $bestTtf) { continue }

      if ($WhatIf) {
        Write-Host ("   WHATIF would copy '{0}' -> '{1}'" -f $bestTtf, $targetPath)
      } else {
        Copy-Item -Path $bestTtf -Destination $targetPath -Force
      }

      $downloaded++
      $ok = $true
      break
    }

    if (-not $ok) {
      $failed.Add($targetFile) | Out-Null
      Write-Warning "FAILED: $targetFile"
    }
  }
} finally {
  if (Test-Path $tmpRoot) { Remove-Item $tmpRoot -Recurse -Force -ErrorAction SilentlyContinue }
}

Write-Host ""
Write-Host "Downloaded: $downloaded"
if ($failed.Count -gt 0) {
  Write-Host ("Failed ({0}):" -f $failed.Count)
  $failed | ForEach-Object { Write-Host (" - " + $_) }
  exit 2
}
exit 0

