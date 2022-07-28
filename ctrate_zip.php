<?php

$papka = __DIR__ . DIRECTORY_SEPARATOR . "data";

$zip = new ZipArchive();


if ($zip->open(__DIR__ . DIRECTORY_SEPARATOR . "glossary.zip", ZipArchive::CREATE) !== TRUE) {
	exit("Невозможно открыть архив\n");
}

// $zip->open('glossary.zip', , ZIPARCHIVE::CREATE);
$files = scandir($papka);
foreach ($files as $file) {
	if ($file == '.' || $file == '..') {
		continue;
	}
	$f = $papka . DIRECTORY_SEPARATOR . $file;
	$zip->addFile($f, $file);
}
$zip->close();
