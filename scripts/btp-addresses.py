#!/usr/bin/python
#https://github.com/cryptean/bitcoinlib
import sys, os, re
from bitcoinrpc.authproxy import AuthServiceProxy

import mysql.connector

RPC_ADDRESS="127.0.0.1:9443"
RPC_USER="YOUR_RPC_USER"
RPC_PASSWORD="YOUR_SUPER_SECRET_RPC_PASSWORD"

class Address:
	def __init__(self, address):
		self.address = address
		self.balance = 0
		self.sent = 0
		self.received = 0
		self.voutList = []		
		self.vinList = []
		self.inTxCount = 0
		self.outTxCount = 0
		
	def addVout(self, txid, amount):
		self.voutList.append({'amount': amount, 'txid': txid})
		self.received = self.received + amount
		self.outTxCount = self.outTxCount + 1
		self.balance = self.received - self.sent
		
	def addVin(self, txid, amount):
		self.vinList.append({'amount': amount, 'txid': txid})
		self.sent = self.sent + amount
		self.inTxCount = self.inTxCount + 1
		self.balance = self.received - self.sent
		
		if self.address == 'coinbase':
			self.balance = self.sent
		
	def getBalance(self):
		print(self.address, 'inTxCount:', self.inTxCount, 'outTxCount:', self.outTxCount, 'balance:', format(self.balance, '.8f'))
		#print('Out transactions')
		#for vou in self.voutList:
		#	print('tx', vou['txid'],'amount', vou['amount'])
			
		#print('\n')
		#print('In transactions')
		#print('\n')
		#for vin in self.vinList:
		#	print('tx', vin['txid'],'amount', vin['amount'])
			

def connect(address, user, password):
	return AuthServiceProxy("http://%s:%s@%s"%(user, password, address))
	
def extract_block_addresses(rpc, block):
	addresses = []
	misses = 0
	
	for tx in block[u'tx']:
		raw_tx = rpc.getrawtransaction(tx, True)
		
		if 'vin' in raw_tx:
			for vin in raw_tx[u'vin']:
				if 'coinbase' in vin:#coinbase transactions
					amount = 0
					for vout in raw_tx[u'vout']:
						amount = amount + vout['value']
						
					addresses.append({'address': 'coinbase', 'amount': amount, 'txid': raw_tx['txid'], 'in': 1})
				elif 'txid' in vin:#no coinbase transactions
					raw_vin_tx = rpc.getrawtransaction(vin['txid'], True)
					ref_vout = raw_vin_tx['vout'][vin['vout']]
					addresses.append({'address': ref_vout['scriptPubKey']['addresses'][0], 'amount': ref_vout['value'], 'txid': vin['txid'], 'in': 1})
		
		if 'vout' not in raw_tx:
			sys.stderr.write("Transaction %s has no 'vout': %s\n"%(tx, raw_tx))
			break
		
		for vout in raw_tx[u'vout']:
			if 'scriptPubKey' not in vout:
				sys.stderr.write("Vout %s of Transaction %s has no 'scriptPubKey'\n"%(vout, tx))
				break
			
			if vout['scriptPubKey']['type'] == 'nulldata' or vout['scriptPubKey']['type'] == 'nonstandard':
				misses = misses + 1
			elif 'addresses' in vout['scriptPubKey']:
				addresses.append({'address': vout['scriptPubKey']['addresses'][0], 'amount': vout['value'], 'txid': raw_tx['txid'], 'in': 0})
			else:
				sys.stderr.write("Can't handle %s transaction output type in transaction %s\n"%(vout["scriptPubKey"]["type"], raw_tx))
			
	
	return addresses

def main():
	print('Copyright (C) 2017 - 2019, Betchip core')
	print('\n')

	addressMap = {}
	
	if len(sys.argv) > 1:
		start_block = int(sys.argv[1])
	else:
		start_block = 1
	
	if len(sys.argv) > 2:
		end_block = int(sys.argv[2])
	else:
		end_block = 0
		
	rpc = connect(RPC_ADDRESS, RPC_USER, RPC_PASSWORD)
	if end_block == 0:
		end_block = rpc.getblockcount()
		
	firstBlock = start_block
	# Position at first block and then iterate over list using nextblockhash to save one extra call
	block_hash = rpc.getblockhash(firstBlock)
	for b in range(start_block, end_block+1):
		try:
			block = rpc.getblock(block_hash)
			
			for addr in extract_block_addresses(rpc, block):
				if addr['address'] not in addressMap:
					address = Address(addr['address'])
					
					if(addr['in'] == 1):
						address.addVin(addr['txid'], addr['amount'])
					else:
						address.addVout(addr['txid'], addr['amount'])
						
					addressMap[addr['address']] = address
				else:
					address = addressMap[addr['address']]
					if(addr['in'] == 1):
						address.addVin(addr['txid'], addr['amount'])
					else:
						address.addVout(addr['txid'], addr['amount'])
			
			if 'nextblockhash' in block:
				block_hash = block['nextblockhash']
		except:
			rpc = connect(RPC_ADDRESS, RPC_USER, RPC_PASSWORD)
			#block_hash = rpc.getblockhash(b)
			#for addr in extract_block_addresses(rpc, block_hash):
			#	print(addr)

	g = open("btpaddress.txt", "a")
	for k in addressMap:
		if addressMap[k].balance > 0:
			g.write('%s,%s\n' % (k, format(addressMap[k].balance, '.8f')))
		#print(k, 'amount:', addressMap[k]['amount'])

	#for k in addressMap:
	#	addressMap[k].getBalance()
		
if __name__ == '__main__':
	main()