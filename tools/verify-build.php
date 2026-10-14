<?php

declare(strict_types=1);

$project = dirname(__DIR__);
$artifact = $argv[1] ?? '';
$metadataPath = $argv[2] ?? '';
$virion = $argv[3] ?? $project . '/virions/libRegRsp';
$expectedDevToolsVersion = $argv[4] ?? '1.0.15';
if($artifact === '' || $metadataPath === '' || !is_file($artifact) || !is_file($metadataPath)){
	throw new RuntimeException('Expected a PHAR and its DevTools metadata');
}
$metadata = json_decode((string) file_get_contents($metadataPath), true, 512, JSON_THROW_ON_ERROR);
if(!is_array($metadata) ||
	($metadata['schema_version'] ?? null) !== 1 ||
	($metadata['success'] ?? false) !== true ||
	($metadata['command'] ?? null) !== 'build' ||
	($metadata['tool']['name'] ?? null) !== 'DevTools' ||
	($metadata['tool']['version'] ?? null) !== $expectedDevToolsVersion){
	throw new RuntimeException('Unexpected DevTools build metadata');
}
$digest = hash_file('sha256', $artifact);
if($digest === false || $digest !== ($metadata['data']['sha256'] ?? null)){
	throw new RuntimeException('PHAR digest does not match build metadata');
}
if(($metadata['data']['plugin']['name'] ?? null) !== 'VisibleBorder' || ($metadata['data']['plugin']['version'] ?? null) !== '1.0.1'){
	throw new RuntimeException('Unexpected plugin identity in build metadata');
}
$phar = new Phar($artifact);
$required = [
	'plugin.yml',
	'LICENSE',
	'src/NhanAZ/VisibleBorder/Main.php',
	'resources/VisibleBorder Pack/manifest.json',
	'META-INF/virions/libRegRsp/LICENSE',
];
foreach($required as $path){
	if(!$phar->offsetExists($path)){
		throw new RuntimeException("Missing PHAR entry: {$path}");
	}
}
$pluginYml = $phar['plugin.yml']->getContent();
if(preg_match('/^version:[ \t]*1\.0\.1\r?$/m', $pluginYml) !== 1){
	throw new RuntimeException('Unexpected plugin version');
}
$manifest = json_decode($phar['resources/VisibleBorder Pack/manifest.json']->getContent(), true, 512, JSON_THROW_ON_ERROR);
if(!is_array($manifest) || ($manifest['header']['version'] ?? null) !== [1, 0, 0]){
	throw new RuntimeException('Unexpected resource pack version');
}
$sourceFound = false;
foreach(new RecursiveIteratorIterator($phar) as $file){
	$path = $file->getPathname();
	if(preg_match('~src/NhanAZ/VisibleBorder/_DevTools/libRegRsp_[a-f0-9]+/libRegRsp\.php$~', $path) === 1){
		$sourceFound = true;
		break;
	}
}
if(!$sourceFound){
	throw new RuntimeException('Shaded libRegRsp source is missing');
}
$normalize = static fn(string $text): string => str_replace(["\r\n", "\r"], "\n", $text);
$pluginLicense = file_get_contents($project . '/LICENSE');
$virionLicense = file_get_contents($virion . '/LICENSE');
if($pluginLicense === false || $normalize($phar['LICENSE']->getContent()) !== $normalize($pluginLicense)){
	throw new RuntimeException('Plugin license differs from source');
}
if($virionLicense === false || $normalize($phar['META-INF/virions/libRegRsp/LICENSE']->getContent()) !== $normalize($virionLicense)){
	throw new RuntimeException('libRegRsp license differs from source');
}
$libRegRspDependencies = array_values(array_filter(
	$metadata['data']['dependencies'] ?? [],
	static fn(mixed $dependency): bool => is_array($dependency) && ($dependency['name'] ?? null) === 'libRegRsp',
));
if(count($libRegRspDependencies) !== 1 ||
	($libRegRspDependencies[0]['version'] ?? null) !== '1.0.6' ||
	($libRegRspDependencies[0]['antigen'] ?? null) !== 'NhanAZ\\libRegRsp'){
	throw new RuntimeException('Unexpected libRegRsp version');
}
echo "VisibleBorder PHAR verification passed\n";
