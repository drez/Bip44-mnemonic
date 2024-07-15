<?php

namespace App\Domains\Bip44;

use Elliptic\EC;
use App\Domains\Bip44\Library\KeyPair;
use App\Domains\Bip44\Library\Helper;

class HDKey
{
    /**
     * Data of key
     *
     * @var array
     */
    protected $data = [
        'version' => null,
        'depth' => 0,
        'index' => '00000000',
        'privateKey' => null,
        'publicKey' => null,
        'chainCode' => null,
        'fingerprint' => '00000000',
        'parentFingerprint' => '00000000',
        'deriveKey' => '00000000',
    ];

    /**
     * Versions
     */
    const BITCOIN_VERSIONS = [
        'private' => 0x0488ADE4,
        'public' => 0x0488B21E
    ];

    /**
     * Offset
     */
    const HARDENED_OFFSET = 0x80000000;

    /**
     * Elliptic curve
     *
     * @EC
     */
    protected $ellipticCurve;

    /**
     * HDKey constructor.
     *
     * @param array $options
     * @throws \Exception
     */
    public function __construct($options = [])
    {
        if(!$this->validateOptions($options)) {
            throw new \Exception('Invalid options');
        }

        $this->data = array_merge($this->data, $options);

        if(isset($options['privateKey'])) {
            $this->generateKeysFromPrivate($options['privateKey']);
        }

        $this->ellipticCurve = new EC('secp256k1');
    }

    /**
     * Getter
     *
     * @param string $name
     * @return mixed
     */
    public function __get(string $name)
    {
        return $this->data[$name];
    }

   

    /**
     * Derive HD key by path
     *
     * @param string $path
     * @return HDKey
     * @throws \Exception
     */
    public function derive(string $path): HDKey
    {
        if (in_array($path, ["m", "M", "m'", "M'"])) {
            return $this;
        }

        $entries = explode('/', $path);

        $HDKey = $this;
        foreach ($entries as $key => $entry) {
            if ($key === 0) {
                if($entry !== 'm') {
                    throw new \Exception('Invalid path');
                }
                continue;
            }

            $childIndex = intval($entry);
            if($childIndex > self::HARDENED_OFFSET) {
                throw new \Exception('Invalid index');
            }

            $hardened = (strlen($entry) > 1) && ($entry[strlen($entry) - 1] === "'");
            if ($hardened) {
                $childIndex += self::HARDENED_OFFSET;
            }

            $HDKey = $HDKey->deriveChild($childIndex);
        }

        return $HDKey;
    }

    /**
     * Derive child key by index
     *
     * @param int $index
     * @return HDKey
     */
    public function deriveChild(int $index): HDKey
    {
        $isHardened = $index >= self::HARDENED_OFFSET;
        $indexHex = $this->convertIndexToHex($index);
        $data = $this->prepareDataString($isHardened, $indexHex);

        list($IL, $IR) = $this->hmac($data, $this->chainCode);

        //$keyPair = new KeyPair($this->ellipticCurve, []);
        try {
           // $privateKey = $keyPair->privateKeyTweakAdd($this->data['privateKey'], bin2hex($IL), 'hex')->toString('hex');
        } catch (\Exception $e) {
            return $this->derive($index + 1);
        }

        $HDKey = new HDKey([
            'depth' => $this->data['depth'] + 1,
            'index' => $index,
            'chainCode' => bin2hex($IR),
            'parentFingerprint' => $this->data['fingerprint'],
            'privateKey' => bin2hex($IL)
        ]);

        //$HDKey->generateKeysFromPrivate(bin2hex($IL));

        return $HDKey;
    }

    /**
     * Generate private key, public key and fingerprint
     *
     * @param string $privateKey
     */
    public function generateKeysFromPrivate(string $privateKey): void
    {
        if(empty($privateKey)) {
            throw new \Exception('Invalid private key');
        }

        $this->data['privateKey'] =  $privateKey;
        $this->data['publicKey'] = $this->getPublicKeyFromPrivate($privateKey);
        $this->data['fingerprint'] = $this->computeFingerprint($this->data['publicKey']);
    }

    /**
     * Compute public key from private using elliptic curve
     *
     * @param string $privateKey
     * @return string
     */
    protected function getPublicKeyFromPrivate(string $privateKey): string
    {
        $this->ellipticCurve = new EC('secp256k1');
        $keyPair = new KeyPair($this->ellipticCurve, [
            'priv' => $privateKey,
            'privEnc' => 'hex'
        ]);

        return $keyPair->getPublic(true, 'hex');
    }

    /**
     * Prepare data string for HMAC hashing
     *
     * @param bool $isHardened
     * @param string $index
     * @return string
     */
    protected function prepareDataString(bool $isHardened, string $index): string
    {
        if($isHardened) {
            assert(!empty($this->data['privateKey']), 'Could not derive hardened child key');

            return $this->privateKeyWithNulls($this->data['privateKey']) . $index;
        }

        return $this->data['publicKey'] . $index;
    }

    /**
     * Encode data to base58 by the version
     *
     * @param $version
     * @return string
     */
    protected function encode($version)
    {
        $data = [
            dechex($version),
            Helper::hex_encode($this->data['depth']),
            Helper::hex_encode(intval($this->data['fingerprint']) !== 0 ? $this->data['parentFingerprint'] : $this->data['fingerprint']),
            $this->convertIndexToHex($this->data['index']),
            $this->data['chainCode'],
            ($version === self::BITCOIN_VERSIONS['private'] ? $this->privateKeyWithNulls($this->data['privateKey']) : $this->data['publicKey'])
        ];

        $string = implode('', $data);
        if (strlen($string) % 2 !== 0) $string = '0' . $string;

        $bs = @pack("H*", $string);
        $checksum = hash("sha256", hash("sha256", $bs, true));
        $checksum = substr($checksum, 0, 8);

        return Helper::base58_encode($string . $checksum);
    }

    /**
     * And nulls for private key
     *
     * @param string $privateKey
     * @return string
     */
    protected function privateKeyWithNulls(string $privateKey): string
    {
        return '00' . $privateKey;
    }

    /**
     * Create HMAC hash and return key/chaincode (IL, IR)
     *
     * @param $data
     * @param $password
     * @return array
     */
    protected function hmac($data, $password): array
    {
        // Generate HMAC hash, and the key/chaincode.
        $I = hash_hmac('sha512', pack('H*', $data), pack('H*', $password), true);
        return [
            substr($I, 0, 32),
            substr($I, 32)
        ];
    }

    /**
     * Compute fingerprint by public key
     *
     * @param string $publicKey
     * @return string
     */
    protected function computeFingerprint(string $publicKey)
    {
        $identifier = Helper::hash160($publicKey);

        return Helper::hex_decode(substr($identifier, 0, 8));
    }

    /**
     * Validate constructor options
     *
     * @param array $options
     * @return bool
     */
    protected function validateOptions(array $options): bool
    {
        foreach($options as $field => $value) {
            if(!array_key_exists($field, $this->data)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Prepare index to hex
     *
     * @param int $index
     * @return string
     */
    protected function convertIndexToHex(int $index): string
    {
        $indexHex = dechex($index);
        return str_repeat('0', 8 - strlen($indexHex)) . $indexHex;
    }
}