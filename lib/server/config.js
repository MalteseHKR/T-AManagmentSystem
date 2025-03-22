require('dotenv').config();

module.exports = {
    database: {
        host: process.env.DB_HOST || '192.168.1.5',
        user: process.env.DB_USER || 'peaky',
        password: process.env.DB_PASSWORD || 'gXkbqb90quESInlDJx1U!',
        database: process.env.DB_NAME || 'garrison_records',
        port: 3306,
        waitForConnections: true,
        connectionLimit: 10,
        queueLimit: 0
    },
    server: {
        vpnHost: '195.158.75.66', //VPN IP
        port: process.env.PORT || 3000,
    },
    upload: {
        destination: './uploads/'
    }
};