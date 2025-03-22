require('dotenv').config();
const path = require('path');

module.exports = {
    database: {
        host: process.env.DB_HOST || '192.168.1.5',
        user: process.env.DB_USER || 'application',
        password: process.env.DB_PASSWORD || 'Xfxgtx295!!',
        database: process.env.DB_NAME || 'garrison_records',
        waitForConnections: true,
        connectionLimit: 10,
        queueLimit: 0
    },
    upload: {

        destination: './uploads/'

    },
    server: {
        port: process.env.PORT || 3000
    }
    };
