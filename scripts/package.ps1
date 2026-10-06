param([string]$Version='0.1.0')
$ErrorActionPreference='Stop'
$repo=Split-Path $PSScriptRoot -Parent
$dist=Join-Path $repo 'dist'
New-Item -ItemType Directory -Path $dist -Force | Out-Null
$output=Join-Path $dist "heaventree-whmcs-mcp-$Version.zip"
if(Test-Path -LiteralPath $output){throw 'Release archive already exists; choose a fresh version. Immutable releases are never overwritten.'}
Add-Type -AssemblyName System.IO.Compression
$stream=[IO.File]::Open($output,[IO.FileMode]::CreateNew)
$zip=[IO.Compression.ZipArchive]::new($stream,[IO.Compression.ZipArchiveMode]::Create)
try {
 $paths=@('modules','clients','docs','README.md','LICENSE','CHANGELOG.md','SECURITY.md')
 foreach($path in $paths){
  $target=Join-Path $repo $path
  $files=if(Test-Path -LiteralPath $target -PathType Container){Get-ChildItem -LiteralPath $target -Recurse -File}else{Get-Item -LiteralPath $target}
  foreach($file in $files){
   $relative=[IO.Path]::GetRelativePath($repo,$file.FullName).Replace('\','/')
   if($relative -match '\.env|\.dpapi|\.sqlite|ioncube|whmcs_mcp/'){throw 'Unsafe release path'}
   $entry=$zip.CreateEntry($relative,[IO.Compression.CompressionLevel]::Optimal)
   $entry.LastWriteTime=[DateTimeOffset]::new(2026,10,6,0,0,0,[TimeSpan]::Zero)
   $destination=$entry.Open();$source=[IO.File]::OpenRead($file.FullName)
   try{$source.CopyTo($destination)}finally{$source.Dispose();$destination.Dispose()}
  }
 }
}finally{$zip.Dispose();$stream.Dispose()}
$check=[IO.Compression.ZipFile]::OpenRead($output)
try {if(!$check.GetEntry('modules/addons/heaventree_mcp/heaventree_mcp.php')){throw 'Install path missing'};if(@($check.Entries | Where-Object FullName -match '\\|^/|\.\./').Count){throw 'Unsafe archive path'};$entries=$check.Entries.Count}finally{$check.Dispose()}
Get-FileHash -LiteralPath $output -Algorithm SHA256 | Select-Object Path,Hash
Write-Output "Verified $entries portable ZIP entries."
