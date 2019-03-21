var btc = require("./coins/btc.js");
var ltc = require("./coins/ltc.js");
var btchp = require("./coins/btchp.js");

module.exports = {
	"BTC": btc,
	"LTC": ltc,
	"BTCHP": btchp,
	"coins":["BTC", "LTC", "BTCHP"]
};