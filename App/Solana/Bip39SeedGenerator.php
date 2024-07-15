<?php

namespace App\Domains\Solana;

use Attestto\SolanaPhpSdk\Util\Buffer;
use App\Domains\Solana\Helper;

class Bip39SeedGenerator
{
    /**
     * @param string $string
     * @return Buffer
     * @throws \Exception
     */
    private function normalize(string $string): Buffer
    {
        if (!class_exists('Normalizer')) {
            if (mb_detect_encoding($string) === 'UTF-8') {
                throw new \Exception('UTF-8 passphrase is not supported without the PECL intl extension installed.');
            } else {
                return new Buffer($string);
            }
        }

        return new Buffer(\Normalizer::normalize($string, \Normalizer::FORM_KD));
    }

    /**
     * @param string $mnemonic
     * @param string $passphrase
     * @return Buffer
     * @throws \Exception
     */
    public function getSeed(string $mnemonic, string $passphrase = ''): Buffer
    {
        return $this::pbkdf2(
            'sha512',
            $this->normalize($mnemonic),
            $this->normalize("mnemonic{$passphrase}"),
            2048,
            64
        );

    }

    public static function pbkdf2(string $algorithm, Buffer $password, Buffer $salt, int $count, int $keyLength): Buffer
    {
        if ($keyLength < 0) {
            throw new \InvalidArgumentException('Cannot have a negative key-length for PBKDF2');
        }

        $algorithm  = strtolower($algorithm);

        if (!in_array($algorithm, hash_algos(), true)) {
            throw new \Exception('PBKDF2 ERROR: Invalid hash algorithm');
        }

        if ($count <= 0 || $keyLength <= 0) {
            throw new \Exception('PBKDF2 ERROR: Invalid parameters.');
        }

        return new Buffer(\hash_pbkdf2($algorithm, $password, $salt, $count, $keyLength, true), $keyLength);
    }

    /**
     * Generate deterministic key from seed phrase
     *
     * @param string $seed
     * @return array
     */
    public static function fromMasterSeed(string $seed, $passphrase): array
    {
        // Generate HMAC hash, and the key/chaincode.
        $I = hash_hmac('sha512', 
            hex2bin($seed),
            "mnemonic{$passphrase}");
        $IL = substr($I, 0, 32);
        $IR = substr($I, 32, 64);

        // Return deterministic key
        return [
            'privateKey' => $IL,
            'chainCode' => $IR
        ];
    }


}