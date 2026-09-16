<?php
/**
 * Script de packaging de l'extension WordPress Secure Ebook Reader
 *
 * Crée l'archive installable 'secure-ebook-reader.zip'
 */

$sourceDir = __DIR__ . '/secure-ebook-reader';
$zipFile   = __DIR__ . '/secure-ebook-reader.zip';

if (!extension_loaded('zip')) {
    die("L'extension PHP ZipArchive est requise.\n");
}

if (file_exists($zipFile)) {
    @unlink($zipFile);
}

$zip = new ZipArchive();
if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("Impossible de créer l'archive {$zipFile}\n");
}

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

$count = 0;
foreach ($files as $name => $file) {
    if (!$file->isDir()) {
        $filePath = $file->getRealPath();
        $relativePath = 'secure-ebook-reader/' . ltrim(substr($filePath, strlen(realpath($sourceDir))), '/\\');
        $relativePath = str_replace('\\', '/', $relativePath);

        // Exclure les fichiers de tests et temporaires du ZIP de production si souhaité
        if (strpos($relativePath, '/tests/') !== false) {
            continue;
        }

        $zip->addFile($filePath, $relativePath);
        $count++;
    }
}

$zip->close();

$size = size_format(filesize($zipFile));
echo "\n============================================================\n";
echo "   PACKAGE SECURE EBOOK READER CRÉÉ AVEC SUCCÈS !\n";
echo "============================================================\n";
echo "Fichier  : {$zipFile}\n";
echo "Taille   : {$size}\n";
echo "Fichiers : {$count} fichiers intégrés\n";
echo "Prêt pour installation dans WordPress (Extensions > Ajouter > Téléverser)\n\n";

function size_format($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    for ($i = 0; $bytes > 1024; $i++) $bytes /= 1024;
    return round($bytes, 2) . ' ' . $units[$i];
}
