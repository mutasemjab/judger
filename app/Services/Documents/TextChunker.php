<?php

namespace App\Services\Documents;

class TextChunker
{
    private int $chunkSize;
    private int $overlap;

    public function __construct(int $chunkSize = 1000, int $overlap = 150)
    {
        $this->chunkSize = $chunkSize;
        $this->overlap = $overlap;
    }

    public function chunk(array $pages): array
    {
        $chunks = [];
        $chunkIndex = 0;

        foreach ($pages as $page) {
            $pageNumber = $page['page'];
            $text = trim($page['text'] ?? '');
            if (empty($text)) {
                continue;
            }

            $words = preg_split('/\s+/', $text);
            $totalWords = count($words);
            $start = 0;

            while ($start < $totalWords) {
                $end = min($start + $this->chunkSize, $totalWords);
                $chunkWords = array_slice($words, $start, $end - $start);
                $chunkText = implode(' ', $chunkWords);

                $chunks[] = [
                    'chunk_index' => $chunkIndex,
                    'page_number' => $pageNumber,
                    'content' => $chunkText,
                    'snippet' => mb_substr($chunkText, 0, 200),
                    'word_count' => count($chunkWords),
                ];

                $chunkIndex++;

                if ($end >= $totalWords) {
                    break;
                }

                $start = $end - $this->overlap;
            }
        }

        return $chunks;
    }
}
