require('dotenv').config();
const crypto = require('crypto');
const DEFAULT_JWT_SECRET = crypto.randomBytes(64).toString('hex');
const JWT_SECRET = process.env.JWT_SECRET || DEFAULT_JWT_SECRET;
const express = require('express');
const mysql = require('mysql2');
const cors = require('cors');
const multer = require('multer');
const path = require('path');
const jwt = require('jsonwebtoken');
const config = require('./config');

const app = express();



// Authentication Middleware
const createAuthenticateToken = (secret) => (req, res, next) => {
    const authHeader = req.headers['authorization'];
    const token = authHeader && authHeader.split(' ')[1];

    if (!token) return res.status(401).json({ message: 'No token provided' });

    jwt.verify(token, secret, (err, user) => {
        if (err) {
            console.error('Token verification error: ', err);
            return res.status(403).json({ message: 'Invalid or expired token' });
        }
        req.user = user;
        next();
    });
};

const authenticateToken = createAuthenticateToken(JWT_SECRET);

// Middleware
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

// Login Route
app.post('/api/login', async (req, res) => {
    try {
        const { email, password } = req.body;
        
        // Basic input validation
        if (!email || !password) {
            return res.status(400).json({ message: 'Email and password are required' });
        }

        // Query to find user
        const [rows] = await db.execute(
            'SELECT l.user_login_id, l.email, l.user_id, ui.user_name, ui.user_surname, ui.user_department, ui.user_email FROM login l JOIN user_information ui ON l.user_id = ui.user_id WHERE l.email = ? AND l.user_login_pass = ?',
            [email, password]
        );

        if (rows.length > 0) {
            const user = rows[0];

            console.log('User found: ', user);

            const token = jwt.sign({
                userId: user.user_id,
                email: user.email
            }, JWT_SECRET, { expiresIn: '24h'});

            console.log('Token generated: ', token);

         // Update login attempts and last login
         await db.execute(
             'UPDATE login SET login_attampts = ?, last_login_attampt = NOW() WHERE user_login_id = ?',
                          [0, user.user_login_id]
        );

         //response
         const response = {
            token: token,
            user: {
            id: user.user_id,
            email: user.user_email,
            full_name: '${user.user_name} ${user.user_surname}',
            department: user.user_department
            }
        };

         console.log('Sending response with token');
            
            res.json(response);

        } else {
            // Increment login attempts
            const [userRecord] = await db.execute(
                'SELECT user_login_id, login_attampts FROM login WHERE email = ?',
                [email]
            );

            if (userRecord.length >0) {
                await db.execute(
                    'UPDATE login SET login_attampts = login_attampts + 1, last_login_attampt = NOW() WHERE user_login_id = ?',
                                 [userRecord[0].user_login_id]
                );
            }

            res.status(401).json({ message: 'Invalid credentials' });
        }
    } catch (error) {
        console.error('Login error:', error);
        res.status(500).json({ error: 'Internal server error' });
    }
});

// Attendance Record Route
app.post('/api/attendance', authenticateToken, upload.single('photo'), async (req, res) => {
    try {
        const userId = req.user.userId;
        const { punch_type, latitude, longitude } = req.body;
        const photoUrl = req.file ? `/uploads/${req.file.filename}` : '';
        const punchDateTime = new Date();
        const now = new Date();

        const [result] = await db.execute(
            'INSERT INTO log_Information (date_time_saved, date_time_event, device_id, user_id, punch_type, photo_url, latitude, longitude, punch_date, punch_time) VALUES (NOW(), NOW(), ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                2,
                userId,
                punch_type, 
                photoUrl, 
                latitude || 0,
                longitude || 0,
                now.toISOString().split('T')[0],
                now.toISOString().split('T')[1].split('.')[0]
            ]
        );

        res.json({
            success: true,
            message: 'Attendance recorded successfully',
            record_id: result.insertId,
            punch_date: now.toISOString().split('T')[0],
            punch_time: now.toISOString().split('T')[1].split('.')[0]
        });
    } catch (error) {
        console.error('Attendance record error:', error);
        res.status(500).json({ error: 'Internal server error' });
    }
});

// Attendance Status route
app.get('/api/attendance/status/:userId', authenticateToken, async (req, res) => {
    try {
        const userId = req.params.userId;

        // Get the latest attendance record for the user
        const [rows] = await db.execute(
            'SELECT punch_type, punch_date, punch_time, photo_url FROM log_Information WHERE user_id = ? ORDER BY date_time_event DESC LIMIT 1',
            [userId]
        );

        if (rows.length > 0) {

            const lastPunch = rows[0];
            const lastPunchDate = new Date(lastPunch.punch_date);

            res.json({
                lastPunchType: rows[0].punch_type,
                lastPunchDate: rows[0].punch_date,
                lastPunchTime: rows[0].punch_time,
                lastPhotoUrl: rows[0].photo_url
            });
        } else {
            res.json({
                lastPunchType: null,
                lastPunchDate: null,
                lastPunchTime: null,
                lastPhotoUrl: null
            });
        }
    } catch (error) {
        console.error('Attendance status error: ', error);
        res.status(500).json({ error: 'Internal server error' });
    }
});

// Leave Request route
const LEAVE_TYPES = {
    'Annual': 1,
    'Sick': 2,
    'Personal': 3
};

app.post('/api/leave', authenticateToken, async (req, res) => {

    // Get a connection from the pool
    const connection = await db.getConnection();

    try {
        console.log('Received leave request', req.body)
        const userId = req.user.userId;
        const { leave_type, start_date, end_date, reason } = req.body;

        // Inpout validation
        if (!leave_type || !start_date || !end_date) {
            console.log('Missing required fields: ', { leave_type, start_date, end_date });
            return res.status(400).json({ message: 'Missing required leave request fields'});
        }

        const leave_type_id = LEAVE_TYPES[leave_type];
        if (!leave_type_id) {
            return res.status(400).json({ message: 'Invslid leave type'});
        }

        // Calculate requested days (excluding weekends)
        const startDate = new Date(start_date);
        const endDate = new Date(end_date);
        let requestedDays = 0;
        for (let d = startDate; d <= endDate; d.setDate(d.getDate() + 1)) {
            if (d.getDay() !== 0 && d.getDay() !== 6) { // Skip Saturdays and Sunday
                requestedDays ++;
            }
        }

        // Check if user has enough leave balance
        const [balanceRows] = await db.execute(
            'SELECT total_days, used_days FROM leave_balances WHERE user_id = ? AND leave_type_id = ? AND year = YEAR(CURRENT_DATE)',
            [userId, leave_type_id]
        );

        if (balanceRows.length ===0) {
            return res.status(400).json({ message: 'No leave balance found for this type'});
        }

        const balance = balanceRows[0];
        const remainingDays = balance.total_days - balance.used_days;

        if (requestedDays > remainingDays) {
            return res.status(400).json({
                message: 'Insufficient leave balance',
                requested: requestedDays,
                remaining: remainingDays
            });
        }

        // Start transaction
        await connection.beginTransaction();

        try {

            console.log('Received leave request', req.body);


        console.log('Inserting leave request for user: ', userId);

            // Insert leave request
            const [result] = await connection.execute(
               'INSERT INTO leave_requests (user_id, leave_type_id, start_date, end_date, reason, status) VALUES (?, ?, ?, ?, ?, ?)', [userId, leave_type_id, start_date, end_date, reason || null, 'pending']);

            console.log('Leave request inserted: ', result);

            // Update leave balance
            await connection.execute(
                'UPDATE leave_balances SET used_days = used_days + ? WHERE user_id = ? AND leave_type_id = ? AND year = YEAR(CURRENT_DATE)',
                [requestedDays, userId, leave_type_id]
            );

            await connection.commit();

            res.json({
                success: true,
                message: 'Leave request submitted successfully',
                request_id: result.insertId,
                days_requested: requestedDays
            });
        } catch (error) {
            await connection.rollback();
            throw error;
        }
    } catch (error) {
        console.error('Leave request error: ', error);
        res.status(500).json({ error: 'Internal server error' });
    } finally {
        connection.release();
    }
});

// Leave balance route
app.get('/api/leave-balance/:userId', authenticateToken, async (req, res) => {
    try {
        const userId = req.params.userId;

        const [rows] = await db.execute(
            'SELECT lt.leave_type_name, lb.total_days, lb.used_days, (lb.total_days - lb.used_days) as remaining_days FROM leave_balances lb JOIN leave_types lt ON lb.leave_type_id = lt.leave_type_id WHERE lb.user_id = ? AND lb.year = YEAR(CURRENT_DATE)',
            [userId]
        );

        const balances = rows.reduce((acc, row) => {
            acc[row.leave_type_name.toLowerCase()] = {
                total: row.total_days,
                used: row.used_days,
                remaining: row.remaining_days
            };
            return acc;
        }, {});

        res.json(balances);
    } catch (error) {
        console.error('Leave balance error: ', error);
        res.status(500).json({ error: 'Internal server error'});
    }
});

// Leave history route
app.get('/api/leave-requests/:userId', authenticateToken, async (req, res) => {
    try {
        const userId = req.params.userId;

        const [rows] = await db.execute(
            'SELECT lr.request_id, lt.leave_type_name as leave_type, lr.start_date, lr.end_date, lr.reason, lr.status, lr.created_at FROM leave_requests lr JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id WHERE lr.user_id = ? ORDER BY lr.created_at DESC',
            [userId]
        );

        res.json(rows);
    } catch (error) {
        console.error('Leave requests error: ', error);
        res.status(500).json({ error: 'Internal server error' });
    }
});

// Get Attendance History Route
app.get('/api/attendance-history/:userId', async (req, res) => {
    try {
        const userId = req.params.userId;

        const [rows] = await db.execute(
            'SELECT * FROM attendance_records WHERE user_id = ? ORDER BY punch_time DESC',
            [userId]
        );

        res.json(rows);
    } catch (error) {
        console.error('Attendance history error:', error);
        res.status(500).json({ error: 'Internal server error' });
    }
});


// Medical certificate upload configuration
const certificateStorage = multer.diskStorage({
  destination: './uploads/certificates',
  filename: function(req, file, cb) {
    cb(null, 'CERT_' + Date.now() + path.extname(file.originalname));
  }
});

const certificateUpload = multer({
  storage: certificateStorage,
  limits: {
    fileSize: 5 * 1024 * 1024, // 5MB limit
  },
  fileFilter: (req, file, cb) => {
    const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf', 'application/octet-stream'];
    if (allowedTypes.includes(file.mimetype)) {
      cb(null, true);
    } else {
      console.log('Invalid file type rejected:', file.mimetype);
      cb(new Error('Invalid file type. Only JPEG, PNG, and PDF are allowed.'), false);
    }
  }
});

// Route for medical certificate upload
app.post('/api/upload-medical-certificate', 
  authenticateToken, 
  certificateUpload.single('certificate'),
  (req, res) => {
    if (!req.file) {
	console.log('No file uploaded');
      return res.status(400).json({ message: 'No file uploaded' });
    }
    res.json({ 
      fileUrl: `/uploads/certificates/${req.file.filename}`
    });
  }
);


// User profile route
app.get('/api/user/:userId', authenticateToken, async (req, res) => {
    try {
        const [rows] = await db.execute(
            ' SELECT ui.*, pp.file_name_link as profile_photo FROM user_information ui LEFT JOIN user_profile_photo pp ON ui.user_id = pp.user_id WHERE ui.user_id = ?',
            [req.params.userId]
        );

        if (rows.length > 0) {
            const user = rows[0];
            res.json({
                user_id: user.user_id,
                email: user.user_email,
                name: user.user_name,
                surname: user.user_surname,
                department: user.user_department,
                title: user.user_title,
                phone: user.user_phone,
                profile_photo: user.profile_photo,
                active: user.user_active === 1
            });
        } else{
            res.status(404).json({ message: 'User not found'});
        }
    } catch (error) {
        console.error('User profile error:', error);
    }
});

// Global Error Handler
app.use((err, req, res, next) => {
    console.error('Unhandled Error:', err);
    res.status(500).json({ 
        error: 'Internal Server Error',
        message: err.message 
    });
});

// Catch-all for undefined routes
app.use((req, res) => {
    res.status(404).json({ message: 'Route not found' });
});

// Start Server
const PORT = config.server.port;
app.listen(PORT, '0.0.0.0', () => {
    console.log(`Server running on port ${PORT}`);
    console.log(`Listening on all network interfaces`);
});
