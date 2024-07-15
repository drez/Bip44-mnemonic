<?php

namespace App\Domains\Bip44;

class BIP44
{
    /**
     * Password
     */
    const MASTER_SECRET = "ed25519 seed";

    /**
     * Generate deterministic key from seed phrase
     *
     * @param string $seed
     * @return HDKey
     */
    public static function fromMasterSeed(string $seed): HDKey
    {

        // Generate HMAC hash, and the key/chaincode.
        $I = hash_hmac('sha512', $seed,  "ed25519 seed", true);
        $IL = substr($I, 0, 32);
        $IR = substr($I, 32, 64);

        // Return deterministic key
        return new HDKey([
            'privateKey' => bin2hex($IL),
            'chainCode' => bin2hex($IR)
        ]);
    }
}