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
const bcrypt = require('bcrypt');
const config = require('./config');
const fs = require('fs');
const { promisify } = require('util');
const copyFile = promisify(fs.copyFile);
const mkdir = promisify(fs.mkdir);
const access = promisify(fs.access);
const sharp = require('sharp'); // You'll need to install this: npm install sharp
const faceapi = require('face-api.js'); // You'll need to install this: npm install face-api.js
const canvas = require('canvas'); // You'll need to install this: npm install canvas
const { Canvas, Image, ImageData } = canvas;
faceapi.env.monkeyPatch({ Canvas, Image, ImageData });

// Add Laravel APP_KEY (handles both base64: prefix and raw key formats)
const LARAVEL_APP_KEY = process.env.APP_KEY || 'base64:4ULzgiK3Nu3wF5eXEzVdTizEBPLKCPuS3TVCZA6LGF4=';
const APP_KEY = LARAVEL_APP_KEY.startsWith('base64:') ? LARAVEL_APP_KEY.substring(7) : LARAVEL_APP_KEY;

console.log('Laravel APP_KEY loaded for password verification');

const app = express();

// Define employee photos directory
const EMPLOYEE_PHOTOS_DIR = '/home/softwaredev/employeephotos';

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
    destination: function(req, file, cb) {
        // Check if uploads directory exists, create if not
        const uploadsDir = config.upload && config.upload.destination ? config.upload.destination : './uploads';
        if (!fs.existsSync(uploadsDir)) {
            fs.mkdirSync(uploadsDir, { recursive: true });
        }
        cb(null, uploadsDir);
    },
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

// Load face-api models
async function loadFaceRecognitionModels() {
    try {
        // Make sure model path is correct based on your server structure
        const modelPath = path.join(__dirname, 'models');
        
        if (!fs.existsSync(modelPath)) {
            console.error('Models directory does not exist. Please create a models directory and download the required models.');
            return false;
        }
        
        // Load face detection, landmark detection and recognition models
        await faceapi.nets.ssdMobilenetv1.loadFromDisk(modelPath);
        await faceapi.nets.faceLandmark68Net.loadFromDisk(modelPath);
        await faceapi.nets.faceRecognitionNet.loadFromDisk(modelPath);
        
        console.log('Face recognition models loaded successfully');
        return true;
    } catch (error) {
        console.error('Error loading face recognition models:', error);
        return false;
    }
}

// Initialize face recognition on server start
let faceRecognitionInitialized = false;
(async () => {
    try {
        // Check if employee photos directory exists, create if not
        try {
            await access(EMPLOYEE_PHOTOS_DIR);
        } catch (error) {
            await mkdir(EMPLOYEE_PHOTOS_DIR, { recursive: true });
            console.log(`Created employee photos directory: ${EMPLOYEE_PHOTOS_DIR}`);
        }

        // Load face recognition models
        faceRecognitionInitialized = await loadFaceRecognitionModels();
    } catch (error) {
        console.error('Error initializing face recognition:', error);
    }
})();

// Middleware to ensure face recognition is initialized
const ensureFaceRecognitionInitialized = (req, res, next) => {
    if (!faceRecognitionInitialized) {
        return res.status(503).json({ message: 'Face recognition service is initializing. Please try again later.' });
    }
    next();
};

// Helper function to process and save employee photo
async function processAndSaveEmployeePhoto(photoPath, userId) {
    try {
        // Create user directory if it doesn't exist
        const userDir = path.join(EMPLOYEE_PHOTOS_DIR, userId.toString());
        try {
            await access(userDir);
        } catch (error) {
            await mkdir(userDir, { recursive: true });
        }

        // Process image (resize, normalize, etc.)
        const processedImage = await sharp(photoPath)
            .resize(600, 600, { fit: 'cover' })
            .jpeg({ quality: 90 })
            .toBuffer();

        // Save processed image
        const facePhotoPath = path.join(userDir, 'face.jpg');
        await fs.promises.writeFile(facePhotoPath, processedImage);

        return facePhotoPath;
    } catch (error) {
        console.error('Error processing employee photo:', error);
        throw error;
    }
}

// Helper function to load user's registered face
async function loadUserFace(userId) {
    try {
        const userPhotoPath = path.join(EMPLOYEE_PHOTOS_DIR, userId.toString(), 'face.jpg');
        
        // Check if photo exists
        try {
            await access(userPhotoPath);
        } catch (error) {
            return null; // No registered face found
        }

        // Load image
        const img = await canvas.loadImage(userPhotoPath);
        
        // Detect face and get descriptor
        const detections = await faceapi.detectSingleFace(img)
            .withFaceLandmarks()
            .withFaceDescriptor();
        
        if (!detections) {
            console.error(`No face detected in registered photo for user: ${userId}`);
            return null;
        }
        
        return detections.descriptor;
    } catch (error) {
        console.error(`Error loading registered face for user: ${userId}`, error);
        return null;
    }
}

// Login Route with support for PHP bcrypt hashes and Laravel APP_KEY
app.post('/api/login', async (req, res) => {
    try {
        const { email, password } = req.body;

        // Basic input validation
        if (!email || !password) {
            return res.status(400).json({ message: 'Email and password are required' });
        }

        console.log(`Login attempt for email: ${email}`);

        // Check if account is already locked
        const [lockStatus] = await db.execute(
            'SELECT user_login_id, login_attempts, last_login_attempt FROM login WHERE email = ?',
            [email]
        );

        if (lockStatus.length > 0 && lockStatus[0].login_attempts >= 4) {
            const lastAttempt = new Date(lockStatus[0].last_login_attempt);
            const currentTime = new Date();
            const diffMinutes = Math.floor((currentTime - lastAttempt) / (1000 * 60));

            // If less than 5 minutes have passed since the lockout
            if (diffMinutes < 5) {
                return res.status(429).json({
                    message: 'Account temporarily locked. Too many failed attempts.',
                    lockout_remaining: 5 - diffMinutes
                });
            }
            // If 5 minutes have passed, we'll allow them to try again
        }

        // Query to find user - Note we're only fetching the user by email, not checking password yet
        const [rows] = await db.execute(
            'SELECT l.user_login_id, l.email, l.user_login_pass, l.user_id, ui.user_name, ui.user_surname, ui.user_department, ui.user_email FROM login l JOIN user_information ui ON l.user_id = ui.user_id WHERE l.email = ?',
            [email]
        );

        if (rows.length === 0) {
            // User not found - Return generic error
            console.log(`User not found for email: ${email}`);
            return res.status(401).json({ message: 'Invalid credentials' });
        }

        const user = rows[0];
        console.log(`User found, comparing passwords for user ID: ${user.user_id}`);

        // Get the stored hash
        let hashedPassword = user.user_login_pass;

        // Check if it's a PHP bcrypt hash ($2y$) and convert to Node's format ($2a$)
        if (hashedPassword.startsWith('$2y$')) {
            hashedPassword = hashedPassword.replace('$2y$', '$2a$');
            console.log('Converted PHP bcrypt hash to Node.js compatible format');
        }

        try {
            // In Laravel, passwords are not typically combined with the APP_KEY for bcrypt verification
            // The key is used for encryption but not for password hashing
            // So we'll try direct comparison with the hash
            const passwordMatch = await bcrypt.compare(password, hashedPassword);
            console.log(`Password match result: ${passwordMatch}`);

            if (passwordMatch) {
                // Password matches - generate token and login user
                const token = jwt.sign({
                    userId: user.user_id,
                    email: user.email
                }, JWT_SECRET, { expiresIn: '24h'});

                console.log('Token generated: ', token);

                // Reset login attempts on successful login
                await db.execute(
                    'UPDATE login SET login_attempts = 0, last_login_attempt = NOW() WHERE user_login_id = ?',
                    [user.user_login_id]
                );

                // Response
                const response = {
                    token: token,
                    user: {
                        id: user.user_id,
                        email: user.user_email,
                        full_name: `${user.user_name} ${user.user_surname}`,
                        department: user.user_department
                    }
                };

                console.log('Sending response with token');
                return res.json(response);
            } else {
                // Password doesn't match - Increment login attempts
                const newAttemptCount = (lockStatus.length > 0)
                    ? lockStatus[0].login_attempts + 1
                    : 1;

                await db.execute(
                    'UPDATE login SET login_attempts = ?, last_login_attempt = NOW() WHERE user_login_id = ?',
                    [newAttemptCount, user.user_login_id]
                );

                // Calculate remaining attempts
                const remainingAttempts = Math.max(0, 4 - newAttemptCount);

                console.log(`Login failed for user ID: ${user.user_id}. Attempt ${newAttemptCount} of 4`);

                // If this was the 4th failed attempt (now at limit)
                if (newAttemptCount >= 4) {
                    return res.status(429).json({
                        message: 'Account locked for 5 minutes due to too many failed attempts',
                        lockout_remaining: 5
                    });
                } else {
                    return res.status(401).json({
                        message: 'Invalid credentials',
                        remaining_attempts: remainingAttempts
                    });
                }
            }
        } catch (bcryptError) {
            console.error('Bcrypt error during password comparison:', bcryptError);
            return res.status(500).json({ error: 'Error verifying password' });
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
        const now = new Date();

        const [result] = await db.execute(
            'INSERT INTO log_Information (date_time_saved, date_time_event, device_id, user_id, punch_type, photo_url, latitude, longitude, punch_date, punch_time) VALUES (NOW(), NOW(), ?, ?, ?, ?, ?, ?, CURDATE(), CURTIME())',
            [
                2,
                userId,
                punch_type,
                photoUrl,
                latitude || 0,
                longitude || 0,
            ]
        );

        // Format time including timezone
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        const localTime = `${hours}:${minutes}:${seconds}`;

        // Format date
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const localDate = `${year}-${month}-${day}`;

        res.json({
            success: true,
            message: 'Attendance recorded successfully',
            record_id: result.insertId,
            punch_date: localDate,
            punch_time: localTime
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
app.get('/api/attendance-history/:userId', authenticateToken, async (req, res) => {
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
    destination: function(req, file, cb) {
        const uploadsDir = './uploads/certificates';
        if (!fs.existsSync(uploadsDir)) {
            fs.mkdirSync(uploadsDir, { recursive: true });
        }
        cb(null, uploadsDir);
    },
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

// Route to register a face photo
app.post('/api/register-face', authenticateToken, upload.single('face_photo'), ensureFaceRecognitionInitialized, async (req, res) => {
    try {
        const userId = req.body.user_id || req.user.userId;
        
        if (!req.file) {
            return res.status(400).json({ message: 'No photo uploaded' });
        }

        // First check if the uploaded image contains a valid face
        const img = await canvas.loadImage(req.file.path);
        const detection = await faceapi.detectSingleFace(img)
            .withFaceLandmarks()
            .withFaceDescriptor();
        
        if (!detection) {
            return res.status(400).json({ message: 'No face detected in the uploaded photo' });
        }

        // Process and save the photo
        const savedPhotoPath = await processAndSaveEmployeePhoto(req.file.path, userId);
        
        // Get relative path for response
        const relativePath = savedPhotoPath.replace(EMPLOYEE_PHOTOS_DIR, '');
        
        // Response
        res.json({
            message: 'Face registered successfully',
            photo_path: relativePath
        });
    } catch (error) {
        console.error('Error registering face:', error);
        res.status(500).json({ message: 'Internal server error during face registration' });
    }
});

// Route to verify a face against the registered face
app.post('/api/verify-face', authenticateToken, upload.single('face_photo'), ensureFaceRecognitionInitialized, async (req, res) => {
    try {
        const userId = req.body.user_id || req.user.userId;
        
        if (!req.file) {
            return res.status(400).json({ message: 'No photo uploaded' });
        }

        // Load registered face descriptor
        const registeredFaceDescriptor = await loadUserFace(userId);
        
        if (!registeredFaceDescriptor) {
            return res.status(404).json({ message: 'No registered face found for this user' });
        }

        // Load and process the uploaded image
        const img = await canvas.loadImage(req.file.path);
        const detection = await faceapi.detectSingleFace(img)
            .withFaceLandmarks()
            .withFaceDescriptor();
        
        if (!detection) {
            return res.status(400).json({
                verified: false,
                confidence: 0,
                message: 'No face detected in the uploaded photo'
            });
        }

        // Calculate face similarity
        const distance = faceapi.euclideanDistance(registeredFaceDescriptor, detection.descriptor);
        const threshold = 0.6; // Lower is more similar, adjust based on testing
        const similarity = 1 - distance; // Convert distance to similarity score (0-1)
        const isMatched = distance < threshold;

        // Response
        res.json({
            verified: isMatched,
            confidence: similarity,
            message: isMatched ? 'Face verified successfully' : 'Face verification failed'
        });
    } catch (error) {
        console.error('Error verifying face:', error);
        res.status(500).json({ message: 'Internal server error during face verification' });
    }
});

// Route to check if a user has a registered face
app.get('/api/face-status/:userId', authenticateToken, async (req, res) => {
    try {
        const userId = req.params.userId;
        
        // Check if user has a registered face
        const userPhotoPath = path.join(EMPLOYEE_PHOTOS_DIR, userId.toString(), 'face.jpg');
        
        let hasRegisteredFace = false;
        try {
            await access(userPhotoPath);
            hasRegisteredFace = true;
        } catch (error) {
            // File doesn't exist, hasRegisteredFace remains false
        }

        res.json({
            has_registered_face: hasRegisteredFace
        });
    } catch (error) {
        console.error('Error checking face status:', error);
        res.status(500).json({ message: 'Internal server error' });
    }
});

// Helper function to hash passwords (for future use when creating/updating user passwords)
async function hashPassword(password) {
    return await bcrypt.hash(password, 12); // Using 12 rounds for bcrypt
}

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