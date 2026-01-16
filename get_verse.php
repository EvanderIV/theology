<?php

// Load the JSON file containing the KJV Bible
$kjvFilePath = __DIR__ . '/versions/KJV.json';

if (!file_exists($kjvFilePath)) {
    http_response_code(500);
    echo json_encode(["error" => "KJV Bible file not found."]);
    exit;
}

$kjvData = json_decode(file_get_contents($kjvFilePath), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to parse KJV Bible JSON."]);
    exit;
}

// Get the verse reference from the query parameter
if (!isset($_GET['reference'])) {
    http_response_code(400);
    echo json_encode(["error" => "No verse reference provided."]);
    exit;
}

$reference = $_GET['reference'];

// Normalize the reference to match the JSON structure
$normalizedReference = preg_replace('/\s+/', ' ', trim($reference));
$normalizedReference = str_replace([':', ';'], ':', $normalizedReference);

// Parse the normalized reference into book, chapter, and verse or range
if (!preg_match('/^(?<Book>.+?)\s(?<Chapter>\d+):(?<VerseRange>\d+(-\d+)?)$/', $normalizedReference, $matches)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid verse reference format."]);
    exit;
}

$book = $matches['Book'];
$chapter = $matches['Chapter'];
$verseRange = $matches['VerseRange'];

// Debugging: Log the normalized reference and structure of KJV.json
file_put_contents(__DIR__ . '/debug.log', "Normalized Reference: $normalizedReference\n", FILE_APPEND);
file_put_contents(__DIR__ . '/debug.log', "Book: $book, Chapter: $chapter, Verse/Range: $verseRange\n", FILE_APPEND);
file_put_contents(__DIR__ . '/debug.log', "Available Books: " . implode(', ', array_keys($kjvData)) . "\n", FILE_APPEND);

// Map common book name variations to their canonical names
$bookAliases = [
    'psalm' => 'psalms',
    'song of solomon' => 'song of songs',
    'canticles' => 'song of songs',
    'apocalypse' => 'revelation'
];

// Normalize the book name to ensure case insensitivity and alias resolution
$book = strtolower($book);
$normalizedBooks = array_change_key_case($kjvData, CASE_LOWER);

if (isset($bookAliases[$book])) {
    $book = $bookAliases[$book];
}

// Check if the book exists in the JSON data
if (!isset($normalizedBooks[$book])) {
    http_response_code(404);
    echo json_encode(["error" => "Book not found."]);
    file_put_contents(__DIR__ . '/debug.log', "Error: Book '$book' not found in KJV.json\n", FILE_APPEND);
    exit;
}

$bookData = $normalizedBooks[$book];

// Check if the chapter exists in the book
if (!isset($bookData[$chapter])) {
    http_response_code(404);
    echo json_encode(["error" => "Chapter not found."]);
    file_put_contents(__DIR__ . '/debug.log', "Error: Chapter '$chapter' not found in Book '$book'\n", FILE_APPEND);
    exit;
}

$chapterData = $bookData[$chapter];

// Check if the verse or range exists in the chapter
if (strpos($verseRange, '-') !== false) {
    list($startVerse, $endVerse) = explode('-', $verseRange);
    $startVerse = (int)$startVerse;
    $endVerse = (int)$endVerse;

    $verses = [];
    for ($verse = $startVerse; $verse <= $endVerse; $verse++) {
        if (isset($chapterData[$verse])) {
            $verses[$verse] = $chapterData[$verse];
        }
    }

    if (empty($verses)) {
        http_response_code(404);
        echo json_encode(["error" => "Verses not found."]);
        file_put_contents(__DIR__ . '/debug.log', "Error: Verses '$verseRange' not found in Chapter '$chapter' of Book '$book'\n", FILE_APPEND);
        exit;
    }

    header('Content-Type: application/json');
    echo json_encode(["reference" => $reference, "text" => $verses]);
} else {
    $verse = $verseRange;
    if (!isset($chapterData[$verse])) {
        http_response_code(404);
        echo json_encode(["error" => "Verse not found."]);
        file_put_contents(__DIR__ . '/debug.log', "Error: Verse '$verse' not found in Chapter '$chapter' of Book '$book'\n", FILE_APPEND);
        exit;
    }

    $verseText = $chapterData[$verse];
    header('Content-Type: application/json');
    echo json_encode(["reference" => $reference, "text" => [$verse => $verseText]]); // Serve single verse as an array
}
