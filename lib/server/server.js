const express = require('express');
const mysql = require('mysql2');
const cors = require('cors');
const multer = require('multer');
const path = require('path');
const jwt = require('jsonwebtoken');
const crypto = require('crypto');
const config = require('./config');

const app = express();

// Generate a default secret if not provided
const DEFAULT_JWT_SECRET = crypto.randomBytes(64).toString('hex');
const JWT_SECRET = process.env.JWT_SECRET || DEFAULT_JWT_SECRET;

// Enhanced Middleware
app.use(cors({
    origin: '*',
    methods: ['GET', 'POST', 'OPTIONS'],
    allowedHeaders: ['Content-Type', 'Authorization']
}));
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Database connection
const db = mysql.createPool(config.database).promise();

// Image Upload Configuration
const storage = multer.diskStorage({
    destination: config.upload.destination,
    filename: function(req, file, cb) {
        cb(null, 'IMG_' + Date.now() + path.extname(file.originalname));
    }
});

const upload = multer({ 
    storage: storage,
    limits: { 
        fileSize: 5 * 1024 * 1024, // 5MB file size limit
        files: 1 // Limit to one file upload
    }
});

// Serve uploaded files
app.use('/uploads', express.static('uploads'));

// Authentication Middleware
const authenticateToken = (req, res, next) => {
    const authHeader = req.headers['authorization'];
    const token = authHeader && authHeader.split(' ')[1];

    if (!token) return res.status(401).json({ message: 'No token provided' });

    jwt.verify(token, JWT_SECRET, (err, user) => {
        if (err) {
            console.error('Token verification error:', err);
            return res.status(403).json({ message: 'Invalid or expired token' });
        }
        req.user = user;
        next();
    });
};

// API Routes
// 1. Login
app.post('/api/login', async (req, res) => {
    try {
        const { email, password } = req.body;
        
        // Basic input validation
        if (!email || !password) {
            return res.status(400).json({ message: 'Email and password are required' });
        }

        const [rows] = await db.execute(
            'SELECT * FROM login WHERE email = ? AND user_login_pass = ?',
            [email, password]
        );

        if (rows.length > 0) {
            const user = rows[0];
            
            res.json({
                token,
                user: {
                    id: user.id,
                    email: user.email,
                    full_name: user.full_name,
                    department: user.department
                }
            });
        } else {
            res.status(401).json({ message: 'Invalid credentials' });
        }
    } catch (error) {
        console.error('Login error:', error);
        res.status(500).json({ error: 'Internal server error' });
    }
});

// 2. Record Attendance
app.post('/api/attendance', authenticateToken, upload.single('photo'), async (req, res) => {
    try {
        const { user_id, punch_type, latitude, longitude } = req.body;
        const photoUrl = req.file ? `/uploads/${req.file.filename}` : null;
        const punchDateTime = new Date(); // Current server time

        // Input validation
        if (!user_id || !punch_type) {
            return res.status(400).json({ message: 'User ID and punch type are required' });
        }

        const [result] = await db.execute(
            `INSERT INTO log_information 
            (user_id, punch_type, photo_url, latitude, longitude, punch_date, punch_time, device_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 2)`,
            [
                user_id, 
                punch_type, 
                photoUrl, 
                latitude, 
                longitude,
                punchDateTime.toISOString().split('T')[0], // Date
                punchDateTime.toISOString().split('T')[1].split('.')[0] // Time
            ]
        );

        res.json({
            success: true,
            message: 'Attendance recorded successfully',
            record_id: result.insertId,
            punch_date: punchDateTime.toISOString().split('T')[0],
            punch_time: punchDateTime.toISOString().split('T')[1].split('.')[0]
        });
    } catch (error) {
        console.error('Attendance record error:', error);
        res.status(500).json({ error: 'Internal server error' });
    }
});

// 3. Get Attendance Status
app.get('/api/attendance/status/:userId', authenticateToken, async (req, res) => {
    try {
        const userId = req.params.userId;
        
        // Query to get the latest attendance record for the user
        const [rows] = await db.execute(
            `SELECT punch_type, punch_date, punch_time 
             FROM attendance_records 
             WHERE user_id = ? 
             ORDER BY punch_date DESC, punch_time DESC 
             LIMIT 1`,
            [userId]
        );

        if (rows.length > 0) {
            res.json({
                lastPunchType: rows[0].punch_type,
                lastPunchDate: rows[0].punch_date,
                lastPunchTime: rows[0].punch_time
            });
        } else {
            res.json({
                lastPunchType: null,
                lastPunchDate: null,
                lastPunchTime: null
            });
        }
    } catch (error) {
        console.error('Attendance status error:', error);
        res.status(500).json({ error: 'Internal server error' });
    }
});

// 4. Submit Leave Request
app.post('/api/leave', authenticateToken, async (req, res) => {
    try {
        const { user_id, leave_type, start_date, end_date, reason } = req.body;
        
        // Input validation
        if (!user_id || !leave_type || !start_date || !end_date) {
            return res.status(400).json({ message: 'Missing required leave request fields' });
        }

        const [result] = await db.execute(
            'INSERT INTO leave_requests (user_id, leave_type, start_date, end_date, reason) VALUES (?, ?, ?, ?, ?)',
            [user_id, leave_type, start_date, end_date, reason]
        );

        res.json({
            success: true,
            message: 'Leave request submitted successfully',
            request_id: result.insertId
        });
    } catch (error) {
        console.error('Leave request error:', error);
        res.status(500).json({ error: 'Internal server error' });
    }
});

// 5. Get Leave Balance
app.get('/api/leave-balance/:userId', authenticateToken, async (req, res) => {
    try {
        const [rows] = await db.execute(
            'SELECT * FROM leave_balances WHERE user_id = ? AND year = YEAR(CURRENT_DATE)',
            [req.params.userId]
        );

        res.json(rows[0] || {
            annual: 0,
            sick: 0,
            personal: 0
        });
    } catch (error) {
        console.error('Leave balance error:', error);
        res.status(500).json({ error: 'Internal server error' });
    }
});

// 6. Get Leave Requests History
app.get('/api/leave-requests/:userId', authenticateToken, async (req, res) => {
    try {
        const userId = req.params.userId;
        
        const [rows] = await db.execute(
            `SELECT id, leave_type, start_date, end_date, reason, status 
             FROM leave_requests 
             WHERE user_id = ? 
             ORDER BY start_date DESC`,
            [userId]
        );

        res.json(rows);
    } catch (error) {
        console.error('Leave requests error:', error);
        res.status(500).json({ error: 'Internal server error' });
    }
});

// 7. Get User Profile
app.get('/api/user/:userId', authenticateToken, async (req, res) => {
    try {
        const [rows] = await db.execute(
            'SELECT user_id, user_email, user_name, user_department, role FROM users WHERE id = ?',
            [req.params.userId]
        );

        if (rows.length > 0) {
            res.json(rows[0]);
        } else {
            res.status(404).json({ message: 'User not found' });
        }
    } catch (error) {
        console.error('User profile error:', error);
        res.status(500).json({ error: 'Internal server error' });
    }
});

// Improved Global Error Handler
app.use((err, req, res, next) => {
    console.error('Unhandled Error:', err);
    res.status(500).json({ 
        error: 'Internal Server Error',
        message: process.env.NODE_ENV === 'production' 
            ? 'An unexpected error occurred' 
            : err.message 
    });
});

// Catch-all for undefined routes
app.use((req, res) => {
    res.status(404).json({ message: 'Route not found' });
});

const PORT = config.server.port;

// Listen on all network interfaces
app.listen(PORT, '0.0.0.0', () => {
    console.log(`Server running on port ${PORT}`);
    console.log(`Listening on all network interfaces`);
});