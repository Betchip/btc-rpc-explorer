<?php

class Address
{
	private $scriptPubKey;
	private $balance;
	private $sent;
	private $received;
	private $voutList;
	private $vinList;
	private $inTxCount;
	private $outTxCount;
	private $lastReceived;
	private $lastSent;

	public function __construct($scriptPubKey)
	{
		$this->scriptPubKey = $scriptPubKey;
		$this->balance = 0;
		$this->sent = 0;
		$this->received = 0;
		$this->voutList = array();
		$this->vinList  = array();
		$this->inTxCount = 0;
		$this->outTxCount = 0;
		$this->lastReceived = NULL;
		$this->lastSent = NULL;
	}
	
	public function appendTransaction($tx)
	{
		if($tx['in'] == 1){
			$this->addVin($tx['txid'], $tx['amount'], $tx['time'], $tx['height']);
		}
		else{
			$this->addVout($tx['txid'], $tx['amount'], $tx['time'], $tx['height']);
		}
	}	
	
	public function getScriptPubKey(){
		return $this->scriptPubKey;
	}
	
	public function getBalance(){
		return $this->balance;
	}
	
	public function getReceived(){
		return $this->received;
	}
	
	public function getSent(){
		return $this->sent;
	}
	
	public function getOutList(){
		return $this->voutList;
	}
	
	public function getInList(){
		return $this->vinList;
	}
	
	public function getLastReceived(){
		return $this->lastReceived;
	}
	
	public function getLastSent(){
		return $this->lastSent;
	}
	
	private function addVout($txid, $amount, $time, $height)
	{
		$this->voutList[] = array('amount'=> $amount, 'txid'=>$txid, 'time'=>$time, 'height'=>$height);
		$this->received = $this->received + $amount;
		$this->outTxCount = $this->outTxCount + 1;
		$this->balance = $this->received - $this->sent;
		$this->lastReceived = $time;
	}

	private function addVin($txid, $amount, $time, $height)
	{
		$this->vinList[] = array('amount'=> $amount, 'txid'=>$txid, 'time'=>$time, 'height'=>$height);
		$this->sent = $this->sent + $amount;
		$this->inTxCount = $this->inTxCount + 1;
		$this->balance = $this->received - $this->sent;
		$this->lastSent = $time;

		if ($this->scriptPubKey == 'coinbase'){
			$this->balance = $this->sent;	
		}
	}	
}

class Settings
{
	private $begin_block;
	private $connection = null;
	const MULTIPLIER = 100000000;//decimalPlaces:0

	public function __construct($host, $database, $username, $password)
	{
		$this->connection = null;
		$this->begin_block = 0;

		$dsn = "mysql:host=$host;dbname=$database;charset=utf8mb4";
		$options = [
		  PDO::ATTR_EMULATE_PREPARES   => false, // turn off emulation mode for "real" prepared statements
		  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, //turn on errors in the form of exceptions
		  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, //make the default fetch be an associative array
		];
		try {
		  $this->connection = new PDO($dsn, $username, $password, $options);
		  
		  $this->loadSettings();
		  
		} catch (Exception $e) {
		  error_log($e->getMessage());
		  exit('Something weird happened'); //something a user can understand
		}
	}

	public function getBeginBlockHeight(){
		return $this->begin_block;
	}
	
	public function saveAddressMap($addrMap = array(), $block_height){
		foreach ($addrMap as $key => $address) {
			if($key != 'coinbase' ){
				$this->saveAddress($address);
				if(count($address->getInList()) > 0){
					$firstTx = $address->getInList()[0];
					$firstTx['amount'] = $address->getSent();
					$this->saveTransactions($key, [$firstTx], $address->getOutList());
				}else{
					$this->saveTransactions($key, $address->getInList(), $address->getOutList());
				}
			}
		}
		
		$this->saveLastJob($block_height);
	}
	
	private function saveAddress($address){
		$pdo = $this->connection;
		$stmt = $pdo->prepare('SELECT id, sent, received, balance, last_received, last_sent FROM addresses WHERE id = ?');
		$stmt->execute([$address->getScriptPubKey()]);
		$arr = $stmt->fetch();
		$last_received = $address->getLastReceived();
		$last_sent = $address->getLastSent();
		
		if($last_sent != NULL){
			$last_sent = $this->toDateTime($address->getLastSent());
		}
		
		if($last_received != NULL){
			$last_received = $this->toDateTime($address->getLastReceived());
		}
		
		if(!$arr){
			$cmd = $pdo->prepare('INSERT INTO addresses VALUES(?, ?, ?, ?, ?, ?)');
			$cmd->execute(
				array($address->getScriptPubKey(), $address->getSent(), $address->getReceived(), $address->getBalance(),
				$last_received, $last_sent)
			);		
			$cmd = NULL;
		}else{
			$cmd = $pdo->prepare('UPDATE addresses SET sent = ?, received = ?, balance = ?, last_received = ?, last_sent = ? WHERE id = ?');
			$arr['sent'] = $arr['sent'] + $address->getSent();
			$arr['received'] = $arr['received'] + $address->getReceived();
			$arr['balance'] = $arr['received'] - $arr['sent'];
			
			if($address->getLastReceived() != NULL){
				$arr['last_received'] = $last_received;
			}
			
			if($address->getLastSent() != NULL){
				$arr['last_sent'] = $last_sent;
			}
			$cmd->execute(
				array($arr['sent'], $arr['received'], $arr['balance'], $arr['last_received'], $arr['last_sent'], $arr['id'])
			);
			$cmd = NULL;
		}
		$stmt = NULL;		
	}
	
	private function saveTransactions($scriptPubKey, $vinList = array(), $voutList = array()){
		$pdo = $this->connection;
		//$cmd = $pdo->prepare('INSERT INTO transactions (id, address_id, type, amount, date_added) SELECT ?, ?, ?, ?, ? FROM DUAL WHERE NOT EXISTS( SELECT 1 FROM transactions WHERE id = ? AND address_id = ?)');
		$cmd = $pdo->prepare('INSERT INTO transactions (txid, address_id, type, amount, block_height, date_added) VALUES(?, ?, ?, ?, ?, ?)');

		foreach ($vinList as $trans) {
			$cmd->execute(array($trans['txid'], $scriptPubKey, 'O', $trans['amount'], $trans['height'], $this->toDateTime($trans['time'])));
		}	
		
		foreach ($voutList as $trans) {
			$cmd->execute(array($trans['txid'], $scriptPubKey, 'I', $trans['amount'], $trans['height'], $this->toDateTime($trans['time'])));
		}			
		$cmd = NULL;		
	}
	
	private function saveLastJob($block_height){
		$pdo = $this->connection;
		$cmd = $pdo->prepare('UPDATE jobs SET last_block = ?, last_executed = ? WHERE id = ?');
		$cmd->execute(array($block_height, date('YmdHis'), 1));
		$cmd = NULL;
	}
	
	private function loadSettings(){
		$pdo = $this->connection;
		$stmt = $pdo->prepare('SELECT last_block, last_executed FROM jobs WHERE id = ?');
		$stmt->execute([1]);
		$arr = $stmt->fetch();
		if(!$arr){
			$this->begin_block = 1;	
			$cmd = $pdo->prepare('INSERT INTO jobs VALUES(?,?,?)');
			$cmd->execute(array(1, 0, date('YmdHis')));
			$cmd = NULL;
		}else{
			$this->begin_block = $arr['last_block'] + 1;
		}
		$stmt = NULL;	
	}
	
	private function toDateTime($unixTimestamp){
		return date("Y-m-d H:i:s", $unixTimestamp);
	}
	
	private function applyMultiplier($amount){
		return self::MULTIPLIER * $amount; 
	}
}
