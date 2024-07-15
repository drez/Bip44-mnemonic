<?php

namespace App\Domains\Solana;

use Attestto\SolanaPhpSdk\SolanaRpcClient;
use Ahc\Env\Loader;

class Helper
{

    static function getDefaultNet()
    {
        (new Loader)->load(_INSTALL_PATH . '../.env');

        $net = strtolower(env('SOL_NET'));

        if(!empty($net)){
            switch($net){
                case 'devnet':
                    return SolanaRpcClient::DEVNET_ENDPOINT;
                case 'mainnet':
                    return SolanaRpcClient::MAINNET_ENDPOINT;
                case 'testnet':
                    return SolanaRpcClient::TESTNET_ENDPOINT;
                default:
                    return $net;
            }
        }else{
            throw new \Exception("No default NET found");
        }
    }

    static function getDefaultNetName()
    {
        (new Loader)->load(_INSTALL_PATH . '../.env');

        $net = strtolower(env('SOL_NET'));

        if(!empty($net)){
            return strtolower($net);
        }

        throw new \Exception("No default NET found");
        
    }

    static function uint8Array(string $binary): array
    {
        return array_values(unpack('C*', $binary));
    }

    static function uint8String(array $uint8Array): string
    {
        return '['.implode(',', $uint8Array).']';
    }
}