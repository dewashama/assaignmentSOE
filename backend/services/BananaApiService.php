<?php
/**
 * BananaApiService - interoperability with the external Banana API.
 *
 * Our PHP server acts as an HTTP client of a third-party web service written and
 * hosted by someone else (https://marcconrad.com/uob/banana/). Communication is
 * HTTPS + JSON:  GET api.php?out=json  ->  {"question": "<image url>", "solution": <digit>}
 *
 * Why the call is made on the SERVER and not in the browser:
 *  - the solution never reaches the player's browser (no cheating via dev tools),
 *  - we can validate the third-party response before trusting it,
 *  - the browser avoids cross-origin (CORS) problems and our own API stays stable
 *    if the third-party format changes.
 *
 * Defensive measures: timeouts, no redirects, HTTP status check, JSON shape
 * validation, and an allow-list for the image host (so a compromised response
 * cannot make our pages load images from arbitrary sites).
 *
 * AI assistance: structured/rewritten with Claude (Anthropic), Nov 2026 - see docs/AI-ACKNOWLEDGEMENTS.md.
 */

declare(strict_types=1);

final class BananaApiService
{
    private const API_URL       = 'https://marcconrad.com/uob/banana/api.php?out=json';
    private const IMAGE_HOST    = 'www.sanfoh.com';
    private const CONNECT_TO    = 10;  // seconds
    private const TOTAL_TO      = 20;  // seconds

    /**
     * @return array{image_url: string, solution: string}
     * @throws RuntimeException with a message that is safe to show to players
     */
    public function fetchPuzzle(): array
    {
        [$response, $status, $error] = $this->httpGet(self::API_URL);

        if ($response === false || $status !== 200) {
            error_log("Banana API error (HTTP $status): $error");
            throw new RuntimeException('The puzzle service is unavailable. Please try again later.');
        }

        return $this->parse($response);
    }

    /**
     * Plain HTTPS GET. Uses cURL when the extension is installed, otherwise PHP
     * streams, so the game still works on servers without php-curl.
     *
     * @return array{0: string|false, 1: int, 2: string} body, HTTP status, error text
     */
    private function httpGet(string $url): array
    {
        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => self::CONNECT_TO,
                CURLOPT_TIMEOUT        => self::TOTAL_TO,
                CURLOPT_FOLLOWLOCATION => false,
            ]);
            $body   = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error  = curl_error($curl);
            curl_close($curl);
            return [$body, $status, $error];
        }

        $context = stream_context_create(['http' => [
            'method'          => 'GET',
            'timeout'         => self::TOTAL_TO,
            'follow_location' => 0,
            'ignore_errors'   => true,   // still give us the status line on 4xx/5xx
        ]]);
        $body   = @file_get_contents($url, false, $context);
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }
        return [$body, $status, $body === false ? 'stream request failed' : ''];
    }

    /**
     * Validate the raw JSON text. Public so it can be unit-tested without a network.
     *
     * @return array{image_url: string, solution: string}
     */
    public function parse(string $json): array
    {
        $data     = json_decode($json, true);
        $imageUrl = is_array($data) ? ($data['question'] ?? '') : '';
        $solution = is_array($data) ? ($data['solution'] ?? null) : null;

        $valid = is_string($imageUrl)
            && filter_var($imageUrl, FILTER_VALIDATE_URL)
            && parse_url($imageUrl, PHP_URL_SCHEME) === 'https'
            && parse_url($imageUrl, PHP_URL_HOST) === self::IMAGE_HOST
            && is_scalar($solution)
            && preg_match('/^\d+$/', (string) $solution);

        if (!$valid) {
            error_log('Banana API returned an unexpected response.');
            throw new RuntimeException('The puzzle service returned an unexpected response.');
        }

        return ['image_url' => $imageUrl, 'solution' => (string) $solution];
    }
}
