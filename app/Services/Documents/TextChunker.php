<?php

namespace App\Services\Documents;

class TextChunker
{
    private int $targetWords;
    private int $overlapWords;
    private int $maxWords;
    private int $minWords;

    public function __construct(int $chunkSize = 420, int $overlap = 60)
    {
        $this->targetWords = max(120, $chunkSize);
        $this->overlapWords = max(0, min($overlap, (int) floor($this->targetWords / 2)));
        $this->maxWords = max($this->targetWords, (int) round($this->targetWords * 1.2));
        $this->minWords = max(80, (int) round($this->targetWords * 0.45));
    }

    public function chunk(array $pages): array
    {
        $segments = $this->buildSegments($pages);

        if ($segments === []) {
            return [];
        }

        $chunks = [];
        $chunkIndex = 0;
        $currentSegments = [];
        $currentWords = 0;
        $startPage = null;
        $endPage = null;

        foreach ($segments as $segment) {
            $segmentWords = $segment['word_count'];

            if (
                $currentSegments !== [] &&
                ($currentWords + $segmentWords) > $this->maxWords &&
                $currentWords >= $this->minWords
            ) {
                $chunks[] = $this->formatChunk($currentSegments, $chunkIndex++, $startPage, $endPage);
                [$currentSegments, $currentWords, $startPage, $endPage] = $this->seedNextChunkFromOverlap($currentSegments);
            }

            $currentSegments[] = $segment;
            $currentWords += $segmentWords;
            $startPage ??= $segment['page_number'];
            $endPage = $segment['page_number'];
        }

        if ($currentSegments !== []) {
            $chunks[] = $this->formatChunk($currentSegments, $chunkIndex, $startPage, $endPage);
        }

        return $chunks;
    }

    private function buildSegments(array $pages): array
    {
        $segments = [];

        foreach ($pages as $page) {
            $pageNumber = (int) ($page['page'] ?? 1);
            $text = $this->normalizeText((string) ($page['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            $blocks = preg_split("/\n{2,}/u", $text) ?: [$text];

            foreach ($blocks as $block) {
                $block = trim($block);

                if ($block === '') {
                    continue;
                }

                foreach ($this->splitLongBlock($block) as $piece) {
                    $piece = trim($piece);

                    if ($piece === '') {
                        continue;
                    }

                    $segments[] = [
                        'page_number' => $pageNumber,
                        'text' => $piece,
                        'word_count' => $this->countWords($piece),
                    ];
                }
            }
        }

        return $segments;
    }

    private function splitLongBlock(string $text): array
    {
        if ($this->countWords($text) <= $this->maxWords) {
            return [$text];
        }

        // ؟ = Arabic question mark, ؛ = Arabic semicolon (common in legal Arabic text),
        // ۔ = Urdu full stop. All are valid sentence boundaries in Arabic documents.
        $sentences = preg_split('/(?<=[\.\!\?؟؛۔])\s+/u', $text) ?: [$text];

        if (count($sentences) <= 1) {
            return $this->splitByWords($text, $this->targetWords);
        }

        $segments = [];
        $current = [];
        $currentWords = 0;

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);

            if ($sentence === '') {
                continue;
            }

            $sentenceWords = $this->countWords($sentence);

            if ($sentenceWords > $this->maxWords) {
                if ($current !== []) {
                    $segments[] = implode(' ', $current);
                    $current = [];
                    $currentWords = 0;
                }

                $segments = array_merge($segments, $this->splitByWords($sentence, $this->targetWords));

                continue;
            }

            if ($current !== [] && ($currentWords + $sentenceWords) > $this->targetWords) {
                $segments[] = implode(' ', $current);
                $current = [];
                $currentWords = 0;
            }

            $current[] = $sentence;
            $currentWords += $sentenceWords;
        }

        if ($current !== []) {
            $segments[] = implode(' ', $current);
        }

        return $segments;
    }

    private function splitByWords(string $text, int $targetWords): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];

        if ($words === []) {
            return [];
        }

        $segments = [];

        for ($offset = 0, $total = count($words); $offset < $total; $offset += $targetWords) {
            $segments[] = implode(' ', array_slice($words, $offset, $targetWords));
        }

        return $segments;
    }

    private function formatChunk(array $segments, int $chunkIndex, ?int $startPage, ?int $endPage): array
    {
        $content = $this->normalizeText(implode("\n\n", array_column($segments, 'text')));
        $resolvedStartPage = $startPage ?? (int) ($segments[0]['page_number'] ?? 1);
        $lastSegment = $segments[array_key_last($segments)] ?? ['page_number' => $resolvedStartPage];
        $resolvedEndPage = $endPage ?? (int) ($lastSegment['page_number'] ?? $resolvedStartPage);

        return [
            'chunk_index' => $chunkIndex,
            'page_number' => $resolvedStartPage,
            'end_page_number' => $resolvedEndPage,
            'content' => $content,
            'snippet' => mb_substr($content, 0, 200),
            'word_count' => $this->countWords($content),
        ];
    }

    private function seedNextChunkFromOverlap(array $segments): array
    {
        if ($this->overlapWords === 0 || $segments === []) {
            return [[], 0, null, null];
        }

        $selected = [];
        $remainingWords = $this->overlapWords;

        for ($index = count($segments) - 1; $index >= 0; $index--) {
            $segment = $segments[$index];
            $segmentWords = (int) ($segment['word_count'] ?? 0);

            if ($segmentWords <= $remainingWords) {
                array_unshift($selected, $segment);
                $remainingWords -= $segmentWords;

                if ($remainingWords === 0) {
                    break;
                }

                continue;
            }

            $tail = $this->lastWords((string) ($segment['text'] ?? ''), $remainingWords);

            if ($tail !== '') {
                array_unshift($selected, [
                    'page_number' => $segment['page_number'],
                    'text' => $tail,
                    'word_count' => $this->countWords($tail),
                ]);
            }

            break;
        }

        if ($selected === []) {
            return [[], 0, null, null];
        }

        $firstSegment = $selected[0];
        $lastSegment = $selected[array_key_last($selected)];

        return [
            $selected,
            array_sum(array_column($selected, 'word_count')),
            (int) ($firstSegment['page_number'] ?? 1),
            (int) ($lastSegment['page_number'] ?? $firstSegment['page_number'] ?? 1),
        ];
    }

    private function lastWords(string $text, int $count): string
    {
        if ($count <= 0) {
            return '';
        }

        $words = preg_split('/\s+/u', trim($text)) ?: [];

        if ($words === []) {
            return '';
        }

        return implode(' ', array_slice($words, -$count));
    }

    private function countWords(string $text): int
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];

        return count(array_filter($words, fn (string $word): bool => $word !== ''));
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
