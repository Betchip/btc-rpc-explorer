var mysql = require('mysql'); //require mysql
var config = require('./config'); //import config file for DB information
var util = require('util');
//create pool connection of MySQL
var pool = mysql.createPool({
    connectionLimit: 100,
    host: config.dbHost,
    user: config.dbUser,
    password: config.dbPassword,
    database: config.dbName
});

pool.getConnection((err, connection) => {
    if (err) {
        if (err.code === 'PROTOCOL_CONNECTION_LOST') {
            console.log('Database connection was closed.');
        }
        if (err.code === 'ER_CON_COUNT_ERROR') {
            console.log('Database has too many connections.')
        }
        if (err.code === 'ECONNREFUSED') {
            console.log('Database connection was refused.');
        }
        if (connection) connection.release();
        return
    }
});

pool.query = util.promisify(pool.query); // Magic happens here.

module.exports = pool; //export the pool variable for globally use