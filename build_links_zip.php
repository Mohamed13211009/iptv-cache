<?php
/**
 * build_links_zip.php
 * Creates a ZIP containing category folders, each with links.txt (movie URLs).
 * Category = first-level folder under /movies
 */

$serverBase = "http://iptv.1stv.xyz:88"; // عدّل لو عندك https
$moviesFolderName = "movies";            // عدّل لو مجلدك اسمه مختلف

$moviesDir = __DIR__ . "/" . $moviesFolderName;
$outZipPath = __DIR__ . "/links_by_category.zip";

$allowedExt = ['mp4','mkv','avi','mov','webm','ts'];

if (!is_dir($moviesDir)) {
  http_response_code(404);
  exit("Movies folder not found: $moviesDir");
}

// اجمع الملفات حسب التصنيف (أول فولدر داخل movies)
$categories = []; // [ "Action" => [url1,url2], ... ]
$allLinks = [];

$it = new RecursiveIteratorIterator(
  new RecursiveDirectoryIterator($moviesDir, FilesystemIterator::SKIP_DOTS),
  RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($it as $file) {
  if ($file->isDir()) continue;

  $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
  if (!in_array($ext, $allowedExt)) continue;

  $fullPath = $file->getPathname();
  $relativeFromMovies = substr($fullPath, strlen($moviesDir) + 1);
  $relativeFromMovies = str_replace("\\", "/", $relativeFromMovies);

  // التصنيف = أول جزء قبل أول /
  $parts = explode("/", $relativeFromMovies);
  $category = $parts[0] ?? "Uncategorized";
  if ($category === "" || $category === "." ) $category = "Uncategorized";

  // URL
  $url = $serverBase . "/" . $moviesFolderName . "/" . str_replace(" ", "%20", $relativeFromMovies);

  if (!isset($categories[$category])) $categories[$category] = [];
  $categories[$category][] = $url;
  $allLinks[] = $url;
}

// ترتيب
ksort($categories);
foreach ($categories as $k => $arr) {
  sort($arr);
  $categories[$k] = $arr;
}
sort($allLinks);

// أنشئ ZIP
$zip = new ZipArchive();
if ($zip->open($outZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
  http_response_code(500);
  exit("Cannot create zip: $outZipPath");
}

// ملف بكل التصنيفات
$zip->addFromString("_ALL/categories.txt", implode("\n", array_keys($categories)) . "\n");

// ملف بكل الروابط
$zip->addFromString("_ALL/all_links.txt", implode("\n", $allLinks) . "\n");

// لكل تصنيف: folder/links.txt
foreach ($categories as $cat => $links) {
  // امنع أسماء مجلدات غريبة داخل zip
  $safeCat = trim($cat);
  $safeCat = preg_replace('/[<>:"\\\\|?*]/', '_', $safeCat);
  if ($safeCat === "") $safeCat = "Uncategorized";

  $zip->addFromString($safeCat . "/links.txt", implode("\n", $links) . "\n");
}

$zip->close();

// نزّل الـ ZIP مباشرة
header("Content-Type: application/zip");
header("Content-Disposition: attachment; filename=\"links_by_category.zip\"");
header("Content-Length: " . filesize($outZipPath));
readfile($outZipPath);
exit;ل
