<?php
// Reference:
//	https://github.com/aceat64/EasyBitcoin-PHP/blob/master/easybitcoin.php

define("RPC_IPADDRESS", "127.0.0.1");
define("RPC_PORT", "9443");
define("RPC_USER", "YOUR_RPC_USER");
define("RPC_PASSWORD", "YOUR_SUPER_SECRET_RPC_PASSWORD");

define("DB_CONNECTION", "mysql");
define("DB_HOST", "127.0.0.1");
define("DB_PORT", "3306");
define("DB_DATABASE", "YOUR_DB");
define("DB_USERNAME", "YOUR_DB");
define("DB_PASSWORD", "YOUR_DB");

require_once('easybitcoin.php');
require_once('models.php');

function extract_block_addresses($rpc, $block){
	$addresses = [];
	
	foreach ($block['tx'] as $tx) {
		$raw_tx = $rpc->getrawtransaction($tx, true);
		
		if(isset($raw_tx['vin'])){
			foreach ($raw_tx['vin'] as $vin) {
				if(isset($vin['coinbase'])){
					$amount = 0;
					foreach ($raw_tx['vout'] as $vout){
						$amount = $amount + $vout['value'];
					}
					//echo "tx: $tx is Coinbase worth: $amount\n";
					$addresses[] = array('address'=> 'coinbase', 'amount'=> $amount, 'txid'=> $tx, 'in'=> 1, 'time'=>$raw_tx['time'], 'blockhash'=>$raw_tx['blockhash'], 'height'=>$block['height']);
				}else if(isset($vin['txid'])){
					$raw_vin_tx = $rpc->getrawtransaction($vin['txid'], true);
					$ref_vout = $raw_vin_tx['vout'][$vin['vout']];
					$addresses[] = array('address'=> $ref_vout['scriptPubKey']['addresses'][0], 'amount'=> $ref_vout['value'], 'txid'=> $raw_tx['txid'], 'in'=> 1, 'time'=>$raw_tx['time'], 'blockhash'=>$raw_tx['blockhash'], 'height'=>$block['height']);
				}
			}			
		}

		foreach ($raw_tx['vout'] as $vout) {
			if(isset($vout['scriptPubKey']) && $vout['scriptPubKey']['type'] != 'nulldata' && $vout['scriptPubKey']['type'] != 'nonstandard'){
				$addresses[] = array('address'=> $vout['scriptPubKey']['addresses'][0], 'amount'=> $vout['value'], 'txid'=> $raw_tx['txid'], 'in'=> 0, 'time'=>$raw_tx['time'], 'blockhash'=>$raw_tx['blockhash'], 'height'=>$block['height']);
			}
		}
	}
	
	return $addresses;
}

$bitcoin = new Bitcoin(RPC_USER, RPC_PASSWORD, RPC_IPADDRESS, RPC_PORT);
$settings = new Settings(DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD);

$blockchaininfo = $bitcoin->getblockchaininfo();

$begin_block = $settings->getBeginBlockHeight();
$end_block = 0;

if(isset($blockchaininfo['blocks'])){
	$end_block = $blockchaininfo['blocks'];
}else{
	exit('Could not connect to RPC Node.');
}

$block_hash = $bitcoin->getblockhash($begin_block);
$block_index = $begin_block;
$addrMap = array();

while ($block_index < ($end_block+1)){
	$block = $bitcoin->getblock($block_hash);
	//echo $block_hash."\n";
	$addresses = extract_block_addresses($bitcoin, $block);
	foreach ($addresses as $addr) {
		$scriptPubKey = $addr['address'];
		if (!isset($addrMap[$scriptPubKey])){
			$addrMap[$scriptPubKey] = new Address($scriptPubKey);
		}
		
		$address = $addrMap[$scriptPubKey];
		$address->appendTransaction($addr);
	}
	
	// BEGIN
	//Let's do one block at a time to avoid big loss in case of failure
	try {
		$settings->saveAddressMap($addrMap, $block_index);
	} catch (Exception $e) {
	  error_log($e->getMessage());
	  exit('ERROR:'.$e->getMessage()); //something a user can understand
	}
	$addrMap = array();
	// END
	
	if(isset($block['nextblockhash'])){
		$block_hash = $block['nextblockhash'];
	}	
	
	$block_index++;
}