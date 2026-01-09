<?php

namespace App\Services\Scrapers;

use App\Models\ScrapedData;
use Illuminate\Support\Str;

/**
 * Example scraper implementation.
 * Customize this class for your specific target website.
 */
class ExampleScraper extends BaseScraper
{
    /**
     * Scrape data from a URL.
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
     * Extract raw data from the crawler.
     * CUSTOMIZE THIS METHOD for your target website.
     */
    protected function extractData($crawler, string $url): array
    {
        // Example extraction - customize for your needs
        return [
            'title' => $this->extractText($crawler, 'h1.title'),
            'description' => $this->extractText($crawler, 'div.description'),
            'price' => $this->extractText($crawler, 'span.price'),
            'category' => $this->extractText($crawler, 'span.category'),
            'author' => $this->extractText($crawler, 'div.author'),
            'date' => $this->extractText($crawler, 'span.date'),
            'images' => $this->extractImages($crawler, 'img.product-image'),
            'metadata' => [
                'url' => $url,
                'scraped_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Helper to safely extract text from a selector.
     */
    protected function extractText($crawler, string $selector, ?string $default = null): ?string
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
    protected function extractAttribute($crawler, string $selector, string $attribute, ?string $default = null): ?string
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
    protected function extractImages($crawler, string $selector): array
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
     * Process and clean the extracted data.
     */
    protected function processData(array $rawData): array
    {
        return [
            'title' => $this->cleanText($rawData['title'] ?? ''),
            'description' => $this->cleanText($rawData['description'] ?? ''),
            'price' => $this->extractPrice($rawData['price'] ?? ''),
            'category' => $this->cleanText($rawData['category'] ?? ''),
            'author' => $this->cleanText($rawData['author'] ?? ''),
            'date' => $this->parseDate($rawData['date'] ?? ''),
            'images' => $rawData['images'] ?? [],
            'metadata' => $rawData['metadata'] ?? [],
        ];
    }

    /**
     * Clean text data.
     */
    protected function cleanText(?string $text): string
    {
        if (!$text) {
            return '';
        }

        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Trim
        $text = trim($text);

        return $text;
    }

    /**
     * Extract numeric price from text.
     */
    protected function extractPrice(?string $text): ?float
    {
        if (!$text) {
            return null;
        }

        // Remove currency symbols and extract number
        $price = preg_replace('/[^0-9.,]/', '', $text);
        $price = str_replace(',', '.', $price);

        return $price ? (float) $price : null;
    }

    /**
     * Parse date string.
     */
    protected function parseDate(?string $dateString): ?string
    {
        if (!$dateString) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($dateString)->toIso8601String();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Validate the extracted data.
     */
    protected function validateData(array $data): bool
    {
        // Customize validation rules for your data
        return !empty($data['title']) || !empty($data['description']);
    }

    /**
     * Save scraped data to database.
     */
    protected function saveData(string $url, array $data): void
    {
        // Generate unique identifier
        $identifier = md5($url . json_encode($data));

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
