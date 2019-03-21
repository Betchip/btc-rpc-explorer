'use strict';

var fs = require('fs');
    //, path = require('path');

var exports = module.exports = {};

function fileExists(filePath) {
    try {
        return fs.statSync(filePath).isFile();
    }
    catch (err) {
        return false;
    }
}

exports.PersistenceManager = function (options) {
    this._lastBlock = 0;
    this._coinSupply = 0;
    this.filePath = options.filePath;
}

exports.PersistenceManager.prototype.save = function (block, coinSupply) {
    var self = this;
    self._lastBlock = block;
    self._coinSupply = self._coinSupply + coinSupply;
    fs.writeFile(self.filePath, JSON.stringify({ block: block, coinSupply: self._coinSupply }), 'utf8', function (err) {
    });
}

exports.PersistenceManager.prototype.load = function () {
    var self = this;
    if (fileExists(self.filePath)) {
       try{
         var obj = JSON.parse(fs.readFileSync(self.filePath, 'utf8'));
         self._lastBlock = obj.block;
         self._coinSupply = obj.coinSupply;
       }catch(error){
       }
    }
}

exports.PersistenceManager.prototype.getLastBlock = function () {
    return this._lastBlock;
}

exports.PersistenceManager.prototype.getCoinSupply = function () {
    return this._coinSupply;
}