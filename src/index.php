<?php

use Attestto\SolanaPhpSdk\Connection;
use Attestto\SolanaPhpSdk\SolanaRpcClient;
use Attestto\SolanaPhpSdk\PublicKey;
use Attestto\SolanaPhpSdk\Transaction as SolTransaction;
use Attestto\SolanaPhpSdk\Programs\SystemProgram;
use App\Domains\Solana\Helper;
use App\Domains\Solana\Bip39SeedGenerator;
use \FurqanSiddiqui\BIP39\BIP39;
use Attestto\SolanaPhpSdk\Keypair;
use App\Domains\Bip44\BIP44;

$mnemonic = BIP39::Generate(12);
$words = implode(" ", $mnemonic->words);
$seed = (new Bip39SeedGenerator())->getSeed($words);

$HDKey = BIP44::fromMasterSeed($seed)->derive("m/44'/501'/0'/0'");

$Keypair = (new Keypair())->fromSeed(hex2bin($HDKey->privateKey));

$obj->setPubKey($Keypair->getPublicKey());
$Secret['base58'] = $Keypair->getSecretKey()->toBase58String();
$Secret['uint8'] = Helper::uint8String(Helper::uint8Array($Keypair->getSecretKey()->toString()));
$obj->setPrivKey(json_encode($Secret));
$obj->setRecovery($words);
$obj->save();
