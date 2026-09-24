<?php

declare(strict_types=1);

use Darvis\ApiX\Support\OAuth1;

/**
 * The example from the X documentation "Creating a signature", so the signer is checked against
 * a value computed by X and not against itself.
 */
function documentedSigner(): OAuth1
{
    return new OAuth1([
        'access_token' => '370773112-GmHxMAgYyLbNEtIKZeRNFsMKPR9EyMZeS9weJAEb',
        'access_token_secret' => 'LswwdoUaIvS8ltyTt5jkRh4J50vUPVVHtR2YPi5kE',
        'consumer_key' => 'xvz1evFS4wEEPTGEFPHBog',
        'consumer_secret' => 'kAcSOqF21Fu85e7zjz7ZN2U4ZRhfV3WpwPAoE3Z7kBw',
    ]);
}

it('computes the signature from the X documentation', function () {
    $signature = documentedSigner()->signature('POST', 'https://api.twitter.com/1.1/statuses/update.json', [
        'include_entities' => 'true',
        'oauth_consumer_key' => 'xvz1evFS4wEEPTGEFPHBog',
        'oauth_nonce' => 'kYjzVBB8Y0ZFabxSWbWovY3uYSQ2pTgmZeNu2VS4cg',
        'oauth_signature_method' => 'HMAC-SHA1',
        'oauth_timestamp' => '1318622958',
        'oauth_token' => '370773112-GmHxMAgYyLbNEtIKZeRNFsMKPR9EyMZeS9weJAEb',
        'oauth_version' => '1.0',
        'status' => 'Hello Ladies + Gentlemen, a signed OAuth request!',
    ]);

    expect($signature)->toBe('hCtSmYh+iHYCEqBWrE7C7hYmtUk=');
});

it('builds a header with every oauth value encoded and the signed query included', function () {
    $header = documentedSigner()->header(
        'POST',
        'https://api.twitter.com/1.1/statuses/update.json',
        ['include_entities' => 'true', 'status' => 'Hello Ladies + Gentlemen, a signed OAuth request!'],
        nonce: 'kYjzVBB8Y0ZFabxSWbWovY3uYSQ2pTgmZeNu2VS4cg',
        timestamp: 1318622958,
    );

    expect($header)
        ->toStartWith('OAuth ')
        ->toContain('oauth_consumer_key="xvz1evFS4wEEPTGEFPHBog"')
        ->toContain('oauth_signature="hCtSmYh%2BiHYCEqBWrE7C7hYmtUk%3D"')
        ->toContain('oauth_timestamp="1318622958"')
        ->not->toContain('status=');
});

it('uses a fresh nonce for every request', function () {
    $signer = documentedSigner();

    expect($signer->header('POST', 'https://api.x.com/2/tweets'))
        ->not->toBe($signer->header('POST', 'https://api.x.com/2/tweets'));
});
