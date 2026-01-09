<?php

namespace App\Services\Scrapers;

use App\Models\ScrapedData;

/**
 * Scraper de ejemplo para sitios de noticias o blogs públicos.
 * Este es un ejemplo funcional que puedes probar directamente.
 */
class QuotesScraper extends BaseScraper
{
    /**
     * Scrape data from URL.
     */
    public function scrape(string $url): ?array
    {
        $html = $this->fetch($url);

        if (!$html) {
            return null;
        }

        $crawler = $this->parse($html);
        
        try {
            $rawData = $this->extractData($crawler, $url);
            
            if (!$rawData || !$this->validateData($rawData)) {
                return null;
            }

            $processedData = $this->processData($rawData);
            
            // Save to database
            $this->saveData($url, $processedData);

            return $processedData;

        } catch (\Exception $e) {
            \Log::error("Error extracting data from {$url}: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Extract raw data from the page.
     */
    protected function extractData($crawler, string $url): array
    {
        $data = [
            'title' => $this->extractTextHelper($crawler, 'title'),
            'meta_description' => $this->extractAttributeHelper($crawler, 'meta[name="description"]', 'content'),
            'headings' => $this->extractHeadings($crawler),
            'links' => $this->extractLinks($crawler),
            'images' => $this->extractImagesHelper($crawler, 'img'),
            'metadata' => [
                'url' => $url,
                'scraped_at' => now()->toIso8601String(),
                'word_count' => $this->countWords($crawler),
            ],
        ];

        return $data;
    }

    /**
     * Helper to safely extract text from a selector.
     */
    protected function extractTextHelper($crawler, string $selector, ?string $default = null): ?string
    {
        try {
            $element = $crawler->filter($selector);
            
            if ($element->count() > 0) {
                return trim($element->text());
            }
        } catch (\Exception $e) {
            // Element not found or error
        }

        return $default;
    }

    /**
     * Helper to extract attributes.
     */
    protected function extractAttributeHelper($crawler, string $selector, string $attribute, ?string $default = null): ?string
    {
        try {
            $element = $crawler->filter($selector);
            
            if ($element->count() > 0) {
                return $element->attr($attribute);
            }
        } catch (\Exception $e) {
            // Element not found or error
        }

        return $default;
    }

    /**
     * Helper to extract multiple images.
     */
    protected function extractImagesHelper($crawler, string $selector): array
    {
        $images = [];

        try {
            $crawler->filter($selector)->each(function ($node) use (&$images) {
                $src = $node->attr('src');
                if ($src) {
                    $images[] = $src;
                }
            });
        } catch (\Exception $e) {
            // Handle error
        }

        return $images;
    }

    /**
     * Extract all headings from the page.
     */
    protected function extractHeadings($crawler): array
    {
        $headings = [];

        for ($i = 1; $i <= 6; $i++) {
            $crawler->filter("h{$i}")->each(function ($node) use (&$headings, $i) {
                $text = trim($node->text());
                if (!empty($text)) {
                    $headings[] = [
                        'level' => $i,
                        'text' => $text,
                    ];
                }
            });
        }

        return $headings;
    }

    /**
     * Extract links from the page.
     */
    protected function extractLinks($crawler): array
    {
        $links = [];

        $crawler->filter('a[href]')->each(function ($node) use (&$links) {
            $href = $node->attr('href');
            $text = trim($node->text());
            
            if (!empty($href) && !empty($text)) {
                $links[] = [
                    'url' => $href,
                    'text' => $text,
                ];
            }
        });

        return array_slice($links, 0, 50); // Limit to first 50 links
    }

    /**
     * Count words in the page.
     */
    protected function countWords($crawler): int
    {
        try {
            $text = $crawler->filter('body')->text();
            return str_word_count($text);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Process the extracted data.
     */
    protected function processData(array $rawData): array
    {
        return [
            'title' => trim($rawData['title'] ?? ''),
            'description' => trim($rawData['meta_description'] ?? ''),
            'headings_count' => count($rawData['headings'] ?? []),
            'headings' => array_slice($rawData['headings'] ?? [], 0, 10),
            'links_count' => count($rawData['links'] ?? []),
            'links' => array_slice($rawData['links'] ?? [], 0, 10),
            'images_count' => count($rawData['images'] ?? []),
            'images' => array_slice($rawData['images'] ?? [], 0, 5),
            'metadata' => $rawData['metadata'] ?? [],
        ];
    }

    /**
     * Validate the extracted data.
     */
    protected function validateData(array $data): bool
    {
        return !empty($data['title']) || !empty($data['headings']);
    }

    /**
     * Save scraped data to database.
     */
    protected function saveData(string $url, array $data): void
    {
        $identifier = md5($url);

        ScrapedData::updateOrCreate(
            ['unique_identifier' => $identifier],
            [
                'source_url' => $url,
                'data' => $data,
                'status' => 'processed',
                'scraped_at' => now(),
            ]
        );
    }
}
