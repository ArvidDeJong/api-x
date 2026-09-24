<?php

declare(strict_types=1);

namespace Darvis\ApiX\Support;

/**
 * Builds the OAuth 1.0a (HMAC-SHA1) Authorization header for a user context request.
 *
 * Only the query string and the oauth_* values are signed. JSON and multipart bodies are not
 * part of the signature, which is why every request this package sends uses one of those.
 */
final class OAuth1
{
    /**
     * @param  array{access_token: string, access_token_secret: string, consumer_key: string, consumer_secret: string}  $credentials
     */
    public function __construct(private readonly array $credentials) {}

    /**
     * The Authorization header value for a request.
     *
     * @param  array<string, string>  $query  Query string or form parameters that are signed.
     */
    public function header(string $method, string $url, array $query = [], ?string $nonce = null, ?int $timestamp = null): string
    {
        $oauth = [
            'oauth_consumer_key' => $this->credentials['consumer_key'],
            'oauth_nonce' => $nonce ?? bin2hex(random_bytes(16)),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => (string) ($timestamp ?? time()),
            'oauth_token' => $this->credentials['access_token'],
            'oauth_version' => '1.0',
        ];

        $oauth['oauth_signature'] = $this->signature($method, $url, array_merge($query, $oauth));

        $parts = [];
        foreach ($oauth as $key => $value) {
            $parts[] = rawurlencode($key).'="'.rawurlencode($value).'"';
        }

        return 'OAuth '.implode(', ', $parts);
    }

    /**
     * The base64 HMAC-SHA1 signature over the method, the URL and the sorted parameters.
     *
     * @param  array<string, string>  $params
     */
    public function signature(string $method, string $url, array $params): string
    {
        $encoded = [];
        foreach ($params as $key => $value) {
            $encoded[rawurlencode($key)] = rawurlencode($value);
        }
        ksort($encoded, SORT_STRING);

        $pairs = [];
        foreach ($encoded as $key => $value) {
            $pairs[] = $key.'='.$value;
        }

        $base = strtoupper($method).'&'.rawurlencode($url).'&'.rawurlencode(implode('&', $pairs));
        $key = rawurlencode($this->credentials['consumer_secret']).'&'.rawurlencode($this->credentials['access_token_secret']);

        return base64_encode(hash_hmac('sha1', $base, $key, true));
    }
}
