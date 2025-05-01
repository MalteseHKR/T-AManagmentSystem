require('dotenv').config();
const crypto = require('crypto');
const express = require('express');
const mysql = require('mysql2');
const cors = require('cors');
const multer = require('multer');
const path = require('path');
const jwt = require('jsonwebtoken');
const bcrypt = require('bcrypt');
const fs = require('fs');
const { promisify } = require('util');
const sharp = require('sharp');
const axios = require('axios');
const FormData = require('form-data');
const config = require('./config/config');
const canvas = require('canvas');
const https = require('https');

// Security Configurations
const DEFAULT_JWT_SECRET = crypto.randomBytes(64).toString('hex');
const JWT_SECRET = process.env.JWT_SECRET || DEFAULT_JWT_SECRET;

// Laravel APP_KEY handling
const LARAVEL_APP_KEY = process.env.APP_KEY || 'base64:default_key_here';
const APP_KEY = LARAVEL_APP_KEY.startsWith('base64:') 
    ? LARAVEL_APP_KEY.substring(7) 
    : LARAVEL_APP_KEY;

// Directory Configurations
const EMPLOYEE_PHOTOS_DIR = '/home/softwaredev/employeephotos';

// Face descriptor cache to improve performance
const faceDescriptorCache = new Map(); // Cache for face descriptors
const CACHE_EXPIRY = 60 * 60 * 1000; // Cache expiry time (1 hour in milliseconds)

// Track face preloading in progress to prevent duplicates
const facePreloadingInProgress = new Map();

// Initialize Express Application
const app = express();

// Middleware Configurations
app.use(cors({
    origin: '*',
    methods: ['GET', 'POST', 'OPTIONS', 'PUT', 'DELETE'],
    allowedHeaders: ['Content-Type', 'Authorization']
}));
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Database Connection
const db = mysql.createPool(config.database).promise();


// Storage Configuration for File Uploads
const storage = multer.diskStorage({
    destination: function(req, file, cb) {
        const uploadsDir = config.upload && config.upload.destination 
            ? config.upload.destination 
            : './uploads';
        
        // Create uploads directory if it doesn't exist
        if (!fs.existsSync(uploadsDir)) {
            fs.mkdirSync(uploadsDir, { recursive: true });
        }
        cb(null, uploadsDir);
    },
    filename: function(req, file, cb) {
        cb(null, 'IMG_' + Date.now() + path.extname(file.originalname));
    }
});

// Multer Upload Configuration
const upload = multer({
    storage: storage,
    limits: {
        fileSize: 5 * 1024 * 1024, // 5MB file size limit
        files: 1 // Limit to one file upload
    },
    fileFilter: (req, file, cb) => {
        // Add a more permissive file filter
        const allowedFields = ['face_photo', 'photo', 'certificate', 'image'];
        if (allowedFields.includes(file.fieldname)) {
            cb(null, true);
        } else {
            cb(new Error(`Unexpected file field: ${file.fieldname}`), false);
        }
    }
});

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

// Create authenticate token middleware
const authenticateToken = createAuthenticateToken(JWT_SECRET);

// Medical Certificate Upload Configuration
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

// Medical Certificate Upload Middleware
const certificateUpload = multer({
    storage: certificateStorage,
    limits: {
        fileSize: 5 * 1024 * 1024, // 5MB limit
    },
    fileFilter: (req, file, cb) => {
        const allowedTypes = [
            'image/jpeg', 
            'image/png', 
            'image/jpg', 
            'application/pdf', 
            'application/octet-stream'
        ];
        if (allowedTypes.includes(file.mimetype)) {
            cb(null, true);
        } else {
            console.log('Invalid file type rejected:', file.mimetype);
            cb(new Error('Invalid file type. Only JPEG, PNG, and PDF are allowed.'), false);
        }
    }
});

// Face status routes section
app.get('/api/face-status/:userId', authenticateToken, async (req, res) => {
    try {
        const userId = req.params.userId;

        // Check filesystem for registered faces
        const files = await fs.promises.readdir(EMPLOYEE_PHOTOS_DIR);
        const userIdStr = userId.toString();
        const userPhotos = files.filter(filename => {
            const parts = filename.split('_');
            const lastPart = parts[parts.length - 1];
            const idPart = lastPart.split('.')[0];
            return idPart === userIdStr;
        });

        res.json({
            has_registered_face: userPhotos.length > 0,
            photo_count: userPhotos.length
        });
    } catch (error) {
        console.error('Error checking face status:', error);
        res.status(500).json({ error: 'Internal server error' });
    }
});

// Get user face photos for ML model training
app.get('/api/user-face-photos/:userId', authenticateToken, async (req, res) => {
    try {
        const userId = req.params.userId;

        // Verify permissions - users can only access their own photos unless admin
        if (req.user.userId != userId) {
            // Check if the requester is an admin
            const [adminCheck] = await db.execute(
                'SELECT role_id FROM user_information WHERE user_id = ?',
                [req.user.userId]
            );

            // Role ID 1 is typically admin - adjust based on your schema
            const isAdmin = adminCheck.length > 0 && adminCheck[0].role_id === 1;

            if (!isAdmin) {
                return res.status(403).json({ message: 'Unauthorized access to user photos' });
            }
        }

        // Ensure user exists
        const [userRows] = await db.execute(
            'SELECT user_name, user_surname FROM user_information WHERE user_id = ?',
            [userId]
        );

        if (userRows.length === 0) {
            return res.status(404).json({ message: 'User not found' });
        }

        const userName = userRows[0].user_name;
        const userSurname = userRows[0].user_surname;

        // Get list of all photo files for this user
        const files = await fs.promises.readdir(EMPLOYEE_PHOTOS_DIR);
        const userPhotos = files.filter(filename =>
            filename.includes(userName) &&
            filename.includes(userSurname) &&
            filename.includes(userId)
        );

        if (userPhotos.length === 0) {
            return res.status(200).json({
                message: 'No face photos found for this user',
                photos: []
            });
        }

        // Create URLs for each photo
        const photoUrls = userPhotos.map(filename => `/api/face-photo/${userId}/${encodeURIComponent(filename)}`);

        res.json({
            photos: photoUrls,
            count: photoUrls.length
        });
    } catch (error) {
        console.error('Error retrieving face photos:', error);
        res.status(500).json({ error: 'Internal server error' });
    }
});


// Endpoint to retrieve a specific face photo by filename
app.get('/api/face-photo/:userId/:filename', authenticateToken, async (req, res) => {
    try {
        const userId = req.params.userId;
        const filename = decodeURIComponent(req.params.filename);

        // Security check - verify the requested file belongs to this user
        if (!filename.includes(userId)) {
            return res.status(403).json({ message: 'Unauthorized access to face photo' });
        }

        // Construct full path to the photo file
        const photoPath = path.join(EMPLOYEE_PHOTOS_DIR, filename);

        // Check if file exists
        if (!fs.existsSync(photoPath)) {
            return res.status(404).json({ message: 'Photo not found' });
        }

        // Send the file
        res.setHeader('Content-Type', 'image/jpeg');
        res.sendFile(photoPath);
    } catch (error) {
        console.error('Error retrieving face photo:', error);
        res.status(500).json({ error: 'Internal server error' });
    }
});

// Serve uploaded files
app.use('/uploads', express.static('uploads'));

// Enhanced helper function to process and save employee photo with orientation fix
async function processAndSaveEmployeePhoto(photoPath, userId) {
    try {
        console.log(`Processing photo for user ID: ${userId}, path: ${photoPath}`);

        // Verify the photo exists
        if (!fs.existsSync(photoPath)) {
            throw new Error(`Photo file does not exist at path: ${photoPath}`);
        }

        // Get user details for naming the file
        const [rows] = await db.execute(
            'SELECT user_name, user_surname FROM user_information WHERE user_id = ?',
            [userId]
        );

        console.log(`Found user details:`, rows.length > 0 ? `${rows[0].user_name} ${rows[0].user_surname}` : 'No user found');

        // Ensure the employee photos directory exists
        if (!fs.existsSync(EMPLOYEE_PHOTOS_DIR)) {
            console.log(`Creating employee photos directory: ${EMPLOYEE_PHOTOS_DIR}`);
            fs.mkdirSync(EMPLOYEE_PHOTOS_DIR, { recursive: true });
        }

        // Get a list of existing photos to determine the next photo number
        const files = await fs.promises.readdir(EMPLOYEE_PHOTOS_DIR);
        const userIdStr = userId.toString();
        const userPhotos = files.filter(filename => {
            const parts = filename.split('_');
            if (parts.length < 2) return false;

            // Extract user ID from the last part (assuming format like Name_Surname_PhotoNum_UserID.jpg)
            const lastPart = parts[parts.length - 1];
            const idPart = lastPart.split('.')[0]; // Remove extension
            return idPart === userIdStr;
        });

        // Determine the next photo number
        const photoNumber = userPhotos.length + 1;
        console.log(`Existing photos for user: ${userPhotos.length}, next photo number: ${photoNumber}`);

        let fileName;
        if (rows.length > 0) {
            const user = rows[0];
            // Format: "Name_Surname_PhotoNumber_UserID.jpg"
            fileName = `${user.user_name}_${user.user_surname}${photoNumber}_${userId}.jpg`;
        } else {
            // Fallback naming if user details aren't available
            fileName = `User_${photoNumber}_${userId}.jpg`;
        }

        console.log(`Generated filename: ${fileName}`);

        // Process image (enhance and fix orientation)
        try {
            // First, get image metadata to check orientation
            const metadata = await sharp(photoPath).metadata();
            console.log(`Image metadata: ${JSON.stringify(metadata, null, 2)}`);

            // Create Sharp processor with auto-orientation
            let processor = sharp(photoPath).rotate(); // Auto-rotate based on EXIF data

            // Check dimensions to see if we need to force portrait orientation
            if (metadata.width > metadata.height) {
                console.log('Image is landscape, rotating to portrait');
                // Force rotate to portrait if landscape
                processor = processor.rotate(90);
            }

            // Apply processing and quality settings
            const processedImage = await processor
                .resize({
                    width: 800,
                    height: 1200,
                    fit: 'inside',
                    withoutEnlargement: true
                })
                .jpeg({ quality: 90 })
                .toBuffer();

            // Save processed image
            const facePhotoPath = path.join(EMPLOYEE_PHOTOS_DIR, fileName);
            await fs.promises.writeFile(facePhotoPath, processedImage);

            console.log(`Successfully saved employee photo to: ${facePhotoPath}`);
            return facePhotoPath;
        } catch (sharpError) {
            console.error('Error processing image with Sharp:', sharpError);

            // Fallback: Copy the original file if image processing fails
            console.log('Falling back to direct file copy');
            const facePhotoPath = path.join(EMPLOYEE_PHOTOS_DIR, fileName);
            fs.copyFileSync(photoPath, facePhotoPath);

            console.log(`Successfully copied original image to: ${facePhotoPath}`);
            return facePhotoPath;
        }
    } catch (error) {
        console.error('Error in processAndSaveEmployeePhoto:', error);
        throw error;
    }
}

// Medical Certificate Upload Route
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

// Image preprocessing helper function to improve detection speed
async function preprocessImageSimple(imagePath) {
    try {
        // Get original image metadata
        const metadata = await sharp(imagePath).metadata();
        console.log(`Original image: ${metadata.width}x${metadata.height}, format: ${metadata.format}`);

        // Choose processing strategy based on image size
        let processor = sharp(imagePath);

        // Only resize if the image is very large
        if (metadata.width > 1200 || metadata.height > 1200) {
            processor = processor.resize({
                width: 800,
                height: 800,
                fit: 'inside',
                withoutEnlargement: true
            });
        }

        // Minimal processing for better performance
        const processedBuffer = await processor
            .jpeg({ quality: 90 })
            .toBuffer();

        console.log('Image preprocessing completed');
        return processedBuffer;
    } catch (error) {
        console.error('Error preprocessing image:', error);
        return null;
    }
}

// Function to analyze image brightness
async function analyzeImageBrightness(imagePath) {
    try {
        // Load the image
        const imageBuffer = await fs.promises.readFile(imagePath);
        const image = await sharp(imageBuffer);
        const metadata = await image.metadata();

        // Get image stats
        const stats = await image.stats();

        // Calculate average brightness across all channels
        let totalBrightness = 0;
        let channels = 0;

        for (const channel in stats.channels) {
            totalBrightness += stats.channels[channel].mean;
            channels++;
        }

        const averageBrightness = totalBrightness / channels;

        console.log(`Image brightness analysis: ${averageBrightness.toFixed(2)}/255`);

        return {
            brightness: averageBrightness,
            width: metadata.width,
            height: metadata.height,
            format: metadata.format
        };
    } catch (error) {
        console.error('Error analyzing image brightness:', error);
        return { brightness: 128, error: error.message }; // Default to mid-brightness on error
    }
}

// Function to adjust image brightness if needed
async function adjustImageIfNeeded(imagePath) {
    try {
        const analysis = await analyzeImageBrightness(imagePath);

        // Skip adjustment if we couldn't analyze
        if (analysis.error) return imagePath;

        // Extract potential device info from EXIF if available
        const metadata = await sharp(imagePath).metadata();
        const isIPhone = metadata.exif &&
            Buffer.from(metadata.exif).toString().toLowerCase().includes('iphone');

        // Different threshold for iPhone vs other devices
        const brightnessThreshold = isIPhone ? 40 : 60;

        console.log(`Device detected: ${isIPhone ? 'iPhone' : 'Other'}, using threshold: ${brightnessThreshold}`);

        // If image is too dark, brighten it
        if (analysis.brightness < brightnessThreshold) {
            console.log(`Image brightness (${analysis.brightness.toFixed(2)}) below threshold, adjusting...`);

            // Calculate how much to brighten (more aggressive for darker images)
            const brightnessAdjustment = Math.min(1.5, 1 + (brightnessThreshold - analysis.brightness) / 50);

            // Create adjusted file path
            const adjustedPath = imagePath + '.adjusted.jpg';

            // Brighten the image
            await sharp(imagePath)
                .modulate({
                    brightness: brightnessAdjustment, // Increase brightness
                    saturation: 1.1 // Slightly increase saturation to compensate
                })
                .toFile(adjustedPath);

            console.log(`Image brightened by factor of ${brightnessAdjustment.toFixed(2)}, saved to ${adjustedPath}`);

            // Use the brightened image
            return adjustedPath;
        } else {
            console.log(`Image brightness (${analysis.brightness.toFixed(2)}) acceptable, no adjustment needed`);
            return imagePath;
        }
    } catch (error) {
        console.error('Error adjusting image brightness:', error);
        return imagePath; // Return original image on error
    }
}

// Simplified route to register a face photo without server-side face recognition
app.post('/api/register-face',
    authenticateToken,
    upload.fields([
        { name: 'face_photo', maxCount: 1 },
        { name: 'photo', maxCount: 1 }
    ]),
    async (req, res) => {
        try {
            const userId = req.body.user_id || req.user.userId;
            const file = req.files?.face_photo?.[0] || req.files?.photo?.[0];

            if (!file) {
                return res.status(400).json({
                    message: 'No photo uploaded',
                    success: false
                });
            }

            // Log file details for debugging
            console.log(`Registering face from file: ${file.originalname}, Size: ${file.size} bytes, MIME: ${file.mimetype}, Field: ${file.fieldname}`);

            // Basic file validation
            if (!['image/jpeg', 'image/png', 'image/jpg'].includes(file.mimetype)) {
                return res.status(400).json({
                    message: 'Unsupported image format. Please upload a JPEG or PNG image.',
                    details: { mimetype: file.mimetype },
                    success: false
                });
            }

            if (file.size > 10 * 1024 * 1024) {  // 10MB limit
                return res.status(400).json({
                    message: 'Image file too large. Maximum size is 10MB.',
                    details: { size: file.size },
                    success: false
                });
            }

            // Process and save the photo
            try {
                // Check and adjust brightness if needed
                const adjustedImagePath = await adjustImageIfNeeded(file.path);

                // Use the possibly adjusted image path
                const savedPhotoPath = await processAndSaveEmployeePhoto(adjustedImagePath, userId);

                // Get relative path for response
                const relativePath = savedPhotoPath.replace(EMPLOYEE_PHOTOS_DIR, '');

                // If we created an adjusted temp file, remove it
                if (adjustedImagePath !== file.path && fs.existsSync(adjustedImagePath)) {
                    fs.unlinkSync(adjustedImagePath);
                    console.log(`Removed temporary adjusted image: ${adjustedImagePath}`);
                }

                // Response
                res.json({
                    message: 'Face registered successfully',
                    photo_path: relativePath,
                    success: true
                });
            } catch (saveError) {
                console.error('Error saving face photo:', saveError);
                return res.status(500).json({
                    message: 'Error saving face photo',
                    error: saveError.message,
                    success: false
                });
            }
        } catch (error) {
            console.error('Error registering face:', error);
            res.status(500).json({
                message: 'Internal server error during face registration',
                error: error.message,
                success: false
            });
        }
    }
);

// Upload Face Photo Route
app.post('/api/upload-face-photo',
    authenticateToken,
    upload.single('face_photo'),
    async (req, res) => {
        try {
            // Get userId from either token or request body
            const userId = req.body.user_id || req.user.userId;
            console.log(`Processing face photo upload for user ID: ${userId}`);

            if (!req.file) {
                console.log('No file uploaded');
                return res.status(400).json({
                    message: 'No file uploaded',
                    success: false
                });
            }

            console.log(`Uploaded file: ${req.file.originalname}, Size: ${req.file.size} bytes, Path: ${req.file.path}`);

            // Process and save the face photo
            try {
                const photoPath = await processAndSaveEmployeePhoto(req.file.path, userId);
                console.log(`Successfully processed and saved to: ${photoPath}`);

                res.json({
                    success: true,
                    message: 'Face photo uploaded successfully',
                    photoPath: photoPath
                });
            } catch (processError) {
                console.error('Error processing photo:', processError);
                res.status(500).json({
                    success: false,
                    message: `Error processing photo: ${processError.message}`
                });
            }
        } catch (error) {
            console.error('Error uploading face photo:', error);
            res.status(500).json({
                success: false,
                error: 'Internal server error',
                message: error.message
            });
        }
    }
);

// User Profile Route
app.get('/api/user/:userId', authenticateToken, async (req, res) => {
    try {
        const userId = req.params.userId;

        // Modified query to join with departments and roles tables
        const [rows] = await db.execute(
            `SELECT ui.*, pp.file_name_link as profile_photo, 
            d.department, r.role
            FROM user_information ui 
            LEFT JOIN user_profile_photo pp ON ui.user_id = pp.user_id
            JOIN departments d ON ui.department_id = d.department_id
            JOIN roles r ON ui.role_id = r.role_id
            WHERE ui.user_id = ?`,
            [userId]
        );

        if (rows.length === 0) {
            return res.status(404).json({ message: 'User not found'});
        }

        const user = rows[0];

        // Check face registration status
        let faceStatus = {
            registered: false,
            cached: false,
            last_updated: null
        };

        // Check filesystem for registered faces
        try {
            const files = await fs.promises.readdir(EMPLOYEE_PHOTOS_DIR);
            const userIdStr = userId.toString();
            const userPhotos = files.filter(filename => {
                const parts = filename.split('_');
                const lastPart = parts[parts.length - 1];
                const idPart = lastPart.split('.')[0];
                return idPart === userIdStr;
            });

            faceStatus.registered = userPhotos.length > 0;
        } catch (error) {
            console.error(`Error checking face registration status: ${error.message}`);
            faceStatus.error = "Error checking registration status";
        }

        // Process profile photo for api consumption
        let profilePhoto = user.profile_photo;

        // If profile photo exists, create a URL path that can be served
        if (profilePhoto) {
            // Extract just the filename from the full path
            const profilePhotoFilename = profilePhoto.split('/').pop();
            profilePhoto = `/profile-pictures/${profilePhotoFilename}`;
        }

        // Build response
        const response = {
            user_id: user.user_id,
            email: user.user_email,
            name: user.user_name,
            surname: user.user_surname,
            department: user.department,
            department_id: user.department_id,
            role: user.role,
            role_id: user.role_id,
            phone: user.user_phone,
            profile_photo: profilePhoto,
            active: user.user_active === 1,
            user_job_start: user.user_job_start,
            face_status: faceStatus
        };

        res.json(response);
    } catch (error) {
        console.error('User profile error:', error);
        res.status(500).json({ error: 'Internal server error' });
    }
});

// Serve profile pictures
app.use('/profile-pictures', 
    authenticateToken, 
    express.static('/home/softwaredev/profile_pictures')
);

// Get All Employees Route
app.get('/api/admin/employees', authenticateToken, async (req, res) => {
    try {
        const [rows] = await db.execute(
            `SELECT ui.user_id, ui.user_name as name, ui.user_surname as surname, 
            ui.user_email as email, ui.user_phone as phone, ui.user_active as active,
            d.department, d.department_id,
            r.role, r.role_id
            FROM user_information ui 
            JOIN departments d ON ui.department_id = d.department_id
            JOIN roles r ON ui.role_id = r.role_id
            ORDER BY ui.user_name, ui.user_surname`
        );

        res.json(rows);
    } catch (error) {
        console.error('Error fetching employees:', error);
        res.status(500).json({ error: 'Internal server error' });
    }
});

// Get Active Users Route
app.get('/api/admin/active-users', authenticateToken, async (req, res) => {
    try {
        const [rows] = await db.execute(
            `SELECT ui.user_id, ui.user_name as name, ui.user_surname as surname, 
            ui.user_email as email, ui.user_active as active,
            d.department, r.role
            FROM user_information ui 
            JOIN departments d ON ui.department_id = d.department_id
            JOIN roles r ON ui.role_id = r.role_id
            WHERE ui.user_active = 1
            ORDER BY ui.user_name, ui.user_surname`
        );

        res.json(rows);
    } catch (error) {
        console.error('Error fetching active users:', error);
        res.status(500).json({ error: 'Internal server error' });
    }
});


// Updated Create a New User Route with password support
app.post('/api/admin/create-user', authenticateToken, async (req, res) => {
    const connection = await db.getConnection();

    try {
        await connection.beginTransaction();

        const {
            name, surname, email, phone, department_id, role_id,
            dob, job_start, admin_id, password // Added password parameter
        } = req.body;

        // Validate required fields
        if (!name || !surname || !email || !department_id || !role_id || !dob || !job_start) {
            return res.status(400).json({ message: 'Missing required fields' });
        }

        // Insert into user_information
        const [userResult] = await connection.execute(
            `INSERT INTO user_information 
            (user_name, user_surname, user_phone, user_email, user_dob, 
            user_job_start, user_active, department_id, role_id) 
            VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)`,
            [name, surname, phone || '', email, dob, job_start, department_id, role_id]
        );

        const userId = userResult.insertId;

        // Use provided password or generate one if not provided
        let finalPassword;
        if (password) {
            finalPassword = password;
        } else {
            // Generate a random password
            const generatePassword = () => {
                const length = 10;
                const charset = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz123456789!@#$%&';
                let password = '';
                for (let i = 0; i < length; i++) {
                    const randomIndex = Math.floor(Math.random() * charset.length);
                    password += charset[randomIndex];
                }
                return password;
            };

            finalPassword = generatePassword();
        }

        const hashedPassword = await bcrypt.hash(finalPassword, 12);

        // Insert into login table
        await connection.execute(
            `INSERT INTO login 
            (email, user_login_pass, password_reset, user_id, login_attempts) 
            VALUES (?, ?, 1, ?, 0)`,
            [email, hashedPassword, userId]
        );

        await connection.commit();

        res.json({
            message: 'User created successfully',
            user_id: userId,
            password: finalPassword
        });
    } catch (error) {
        await connection.rollback();
        console.error('Error creating user:', error);

        // Check for duplicate email
        if (error.code === 'ER_DUP_ENTRY') {
            return res.status(400).json({ message: 'Email already exists' });
        }

        res.status(500).json({ error: 'Internal server error' });
    } finally {
        connection.release();
    }
});

// Profile Photo Upload Configuration
const profilePhotoStorage = multer.diskStorage({
    destination: function(req, file, cb) {
        const uploadsDir = '/home/softwaredev/profile_pictures';
        if (!fs.existsSync(uploadsDir)) {
            fs.mkdirSync(uploadsDir, { recursive: true });
        }
        cb(null, uploadsDir);
    },
    filename: function(req, file, cb) {
        const ext = path.extname(file.originalname);
        const timestamp = new Date().toISOString().replace(/[-:.]/g, '').substring(0, 14);
        const userId = req.body.user_id || req.user.userId;
        cb(null, `user_${userId}_${timestamp}${ext}`);
    }
});

// Profile Photo Upload Middleware - Accept both 'profile_photo' and 'photo' field names
const profilePhotoUpload = multer({
    storage: profilePhotoStorage,
    limits: {
        fileSize: 5 * 1024 * 1024, // 5MB limit
    },
    fileFilter: (req, file, cb) => {
        // Accept both field names
        if (['profile_photo', 'photo'].includes(file.fieldname)) {
            const allowedTypes = [
                'image/jpeg',
                'image/png',
                'image/jpg'
            ];
            if (allowedTypes.includes(file.mimetype)) {
                cb(null, true);
            } else {
                console.log('Invalid file type rejected:', file.mimetype);
                cb(new Error('Invalid file type. Only JPEG and PNG are allowed.'), false);
            }
        } else {
            console.log(`Unexpected field name: ${file.fieldname}`);
            cb(new Error(`Unexpected field name: ${file.fieldname}`), false);
        }
    }
});

// Update the profile photo upload route to also fix orientation
app.post('/api/upload-profile-photo',
    authenticateToken,
    // Use fields instead of single to accept both field names
    profilePhotoUpload.fields([
        { name: 'profile_photo', maxCount: 1 },
        { name: 'photo', maxCount: 1 }
    ]),
    async (req, res) => {
        try {
            // Get the file from either field name
            const file = req.files?.profile_photo?.[0] || req.files?.photo?.[0];

            if (!file) {
                console.log('No profile photo uploaded');
                return res.status(400).json({ message: 'No profile photo uploaded' });
            }

            const userId = req.body.user_id || req.user.userId;

            console.log(`Processing profile photo for user ID: ${userId}, field: ${file.fieldname}`);

            // Get user details for creating a meaningful filename
            const [userRows] = await db.execute(
                'SELECT user_name, user_surname FROM user_information WHERE user_id = ?',
                [userId]
            );

            if (userRows.length === 0) {
                return res.status(404).json({ message: 'User not found' });
            }

            const user = userRows[0];

            // Create a better filename with user information
            const oldPath = file.path;
            const fileExt = path.extname(file.filename);
            const newFilename = `${user.user_name}_${user.user_surname}profile_${userId}${fileExt}`;
            const newPath = path.join('/home/softwaredev/profile_pictures', newFilename);

            try {
                // First, get image metadata to check orientation
                const metadata = await sharp(oldPath).metadata();

                // Create Sharp processor with auto-orientation
                let processor = sharp(oldPath).rotate(); // Auto-rotate based on EXIF data

                // Check dimensions to see if we need to force portrait orientation
                if (metadata.width > metadata.height) {
                    console.log('Profile image is landscape, rotating to portrait');
                    // Force rotate to portrait if landscape
                    processor = processor.rotate(90);
                }

                // Apply processing - making it square for profile photos
                const processedImage = await processor
                    .resize({
                        width: 400,
                        height: 400,
                        fit: 'cover', // Use cover for profile photos to get a square image
                        position: 'face', // Try to focus on face if detected
                    })
                    .jpeg({ quality: 85 })
                    .toBuffer();

                // Save processed image
                await fs.promises.writeFile(newPath, processedImage);
                console.log(`Successfully processed and saved profile photo to: ${newPath}`);
            } catch (sharpError) {
                console.error('Error processing profile image with Sharp:', sharpError);

                // Fallback: Copy the original file if image processing fails
                fs.copyFileSync(oldPath, newPath);
                console.log(`Fallback: Copied original profile image to: ${newPath}`);
            }

            // Check if there's an existing profile photo entry
            const [existingRows] = await db.execute(
                'SELECT photo_id FROM user_profile_photo WHERE user_id = ?',
                [userId]
            );

            if (existingRows.length > 0) {
                // Update existing entry
                await db.execute(
                    'UPDATE user_profile_photo SET file_name_link = ? WHERE user_id = ?',
                    [newPath, userId]
                );
            } else {
                // Insert new entry
                await db.execute(
                    'INSERT INTO user_profile_photo (file_name_link, user_id) VALUES (?, ?)',
                    [newPath, userId]
                );
            }

            // Create the relative URL for response
            const relativeUrl = `/profile-pictures/${path.basename(newPath)}`;

            res.json({
                success: true,
                message: 'Profile photo uploaded successfully',
                fileUrl: relativeUrl
            });
        } catch (error) {
            console.error('Error uploading profile photo:', error);
            res.status(500).json({
                success: false,
                error: 'Internal server error',
                message: error.message
            });
        }
    }
);

// Update User Status Route
app.put('/api/admin/update-user-status', authenticateToken, async (req, res) => {
    const connection = await db.getConnection();

    try {
        await connection.beginTransaction();

        const { user_id, active, admin_id } = req.body;

        // Validate required fields
        if (!user_id || active === undefined || !admin_id) {
            return res.status(400).json({ message: 'Missing required fields' });
        }

        // Update user status
        await connection.execute(
            'UPDATE user_information SET user_active = ? WHERE user_id = ?',
            [active ? 1 : 0, user_id]
        );

        // Log the action
        await connection.execute(
            `INSERT INTO admin_logs 
            (admin_id, action, affected_user_id, timestamp) 
            VALUES (?, ?, ?, NOW())`,
            [admin_id, active ? 'activate_user' : 'deactivate_user', user_id]
        );

        await connection.commit();

        res.json({
            message: `User ${active ? 'activated' : 'deactivated'} successfully`
        });
    } catch (error) {
        await connection.rollback();
        console.error('Error updating user status:', error);
        res.status(500).json({ error: 'Internal server error' });
    } finally {
        connection.release();
    }
});

// Reset User Password Route
app.post('/api/admin/reset-password', authenticateToken, async (req, res) => {
    const connection = await db.getConnection();

    try {
        await connection.beginTransaction();

        const { user_id, admin_id } = req.body;

        // Validate required fields
        if (!user_id || !admin_id) {
            return res.status(400).json({ message: 'Missing required fields' });
        }

        // Generate a random password
        const generatePassword = () => {
            const length = 10;
            const charset = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz123456789!@#$%&';
            let password = '';
            for (let i = 0; i < length; i++) {
                const randomIndex = Math.floor(Math.random() * charset.length);
                password += charset[randomIndex];
            }
            return password;
        };

        const password = generatePassword();
        const hashedPassword = await bcrypt.hash(password, 12);

        // Update the user's password
        await connection.execute(
            'UPDATE login SET user_login_pass = ?, password_reset = 1 WHERE user_id = ?',
            [hashedPassword, user_id]
        );

        // Log the action
        await connection.execute(
            `INSERT INTO admin_logs 
            (admin_id, action, affected_user_id, timestamp) 
            VALUES (?, 'reset_password', ?, NOW())`,
            [admin_id, user_id]
        );

        await connection.commit();

        res.json({
            message: 'Password reset successfully',
            password: password
        });
    } catch (error) {
        await connection.rollback();
        console.error('Error resetting password:', error);
        res.status(500).json({ error: 'Internal server error' });
    } finally {
        connection.release();
    }
});

// Get Departments Route
app.get('/api/admin/departments', authenticateToken, async (req, res) => {
    try {
        const [rows] = await db.execute(
            'SELECT department_id, department FROM departments ORDER BY department'
        );

        res.json(rows);
    } catch (error) {
        console.error('Error fetching departments:', error);
        res.status(500).json({ error: 'Internal server error' });
    }
});

// Get Roles Route
app.get('/api/admin/roles', authenticateToken, async (req, res) => {
    try {
        const [rows] = await db.execute(
            'SELECT role_id, role FROM roles ORDER BY role'
        );

        res.json(rows);
    } catch (error) {
        console.error('Error fetching roles:', error);
        res.status(500).json({ error: 'Internal server error' });
    }
});


// Leave Types Mapping
const LEAVE_TYPES = {
    'Annual': 1,
    'Sick': 2,
    'Personal': 3
};

// Submit Leave Request Route
app.post('/api/leave', authenticateToken, async (req, res) => {
    const connection = await db.getConnection();

    try {
        console.log('Received leave request', req.body)
        const userId = req.user.userId;
        const { leave_type, start_date, end_date, reason, is_full_day, start_time, end_time, medical_certificate } = req.body;

        // Input validation
        if (!leave_type || !start_date || !end_date) {
            console.log('Missing required fields: ', { leave_type, start_date, end_date });
            return res.status(400).json({ message: 'Missing required leave request fields'});
        }

        const leave_type_id = LEAVE_TYPES[leave_type];
        if (!leave_type_id) {
            return res.status(400).json({ message: 'Invalid leave type'});
        }

        // Calculate requested days (excluding weekends)
        const startDate = new Date(start_date);
        const endDate = new Date(end_date);
        let requestedDays = 0;
        for (let d = new Date(startDate); d <= endDate; d.setDate(d.getDate() + 1)) {
            if (d.getDay() !== 0 && d.getDay() !== 6) { // Skip Saturdays and Sunday
                // For partial days, count as half day
                requestedDays += (is_full_day === false) ? 0.5 : 1;
            }
        }

        // Check if user has enough leave balance
        const [balanceRows] = await db.execute(
            'SELECT total_days, used_days FROM leave_balances WHERE user_id = ? AND leave_type_id = ? AND year = YEAR(CURRENT_DATE)',
            [userId, leave_type_id]
        );

        if (balanceRows.length === 0) {
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
            console.log('Inserting leave request for user: ', userId);

            // Insert leave request with the new fields
            const [result] = await connection.execute(
                `INSERT INTO leave_requests 
                (user_id, leave_type_id, start_date, end_date, reason, status, 
                is_full_day, start_time, end_time, medical_certificate) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
                [
                    userId,
                    leave_type_id,
                    start_date,
                    end_date,
                    reason || null,
                    'pending',
                    is_full_day === false ? 0 : 1,
                    start_time || null,
                    end_time || null,
                    medical_certificate || null
                ]
            );

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

// Submit Preliminary Sick Leave Request Route
app.post('/api/leave/preliminary-sick', authenticateToken, async (req, res) => {
    const connection = await db.getConnection();

    try {
        await connection.beginTransaction();

        const userId = req.user.userId;
        const { reason } = req.body;

        // Sick leave type ID is 2 (based on your database schema)
        const leave_type_id = 2;

        // Use current date for both start_date and end_date as placeholders
        // These will be updated when the request is completed
        const currentDate = new Date().toISOString().split('T')[0]; // Format as YYYY-MM-DD

        // Insert preliminary leave request with status 'Pending Certificate'
        // Include start_date and end_date fields with current date as placeholder
        const [result] = await connection.execute(
            'INSERT INTO leave_requests (user_id, leave_type_id, reason, status, request_date, start_date, end_date) VALUES (?, ?, ?, ?, CURDATE(), ?, ?)',
            [userId, leave_type_id, reason || 'Sick leave - medical certificate to be provided', 'Pending Certificate', currentDate, currentDate]
        );

        console.log('Preliminary sick leave request inserted:', result);

        await connection.commit();

        res.json({
            success: true,
            message: 'Preliminary sick leave request submitted successfully',
            id: result.insertId,
            request_date: new Date().toISOString().split('T')[0]
        });
    } catch (error) {
        await connection.rollback();
        console.error('Preliminary sick leave request error:', error);
        res.status(500).json({ error: 'Internal server error', details: error.message });
    } finally {
        connection.release();
    }
});

// Complete Sick Leave Request Route
app.put('/api/leave/complete-sick/:requestId', authenticateToken, async (req, res) => {
    const connection = await db.getConnection();

    try {
        await connection.beginTransaction();

        const requestId = req.params.requestId;
        const userId = req.user.userId;
        const { start_date, end_date, medical_certificate, is_full_day, start_time, end_time } = req.body;

        // Validate required fields
        if (!start_date || !end_date || !medical_certificate) {
            return res.status(400).json({ message: 'Missing required fields' });
        }

        // Verify the request belongs to the user
        const [requestRows] = await connection.execute(
            'SELECT request_id, user_id, leave_type_id FROM leave_requests WHERE request_id = ? AND status = ?',
            [requestId, 'Pending Certificate']
        );

        if (requestRows.length === 0) {
            return res.status(404).json({ message: 'Pending sick leave request not found' });
        }

        if (requestRows[0].user_id != userId) {
            return res.status(403).json({ message: 'Unauthorized access to this leave request' });
        }

        // Calculate requested days (excluding weekends)
        const startDate = new Date(start_date);
        const endDate = new Date(end_date);
        let requestedDays = 0;
        for (let d = new Date(startDate); d <= endDate; d.setDate(d.getDate() + 1)) {
            if (d.getDay() !== 0 && d.getDay() !== 6) { // Skip Saturdays and Sunday
                // For partial days, count as half day
                requestedDays += is_full_day ? 1 : 0.5;
            }
        }

        // Check if user has enough leave balance
        const [balanceRows] = await connection.execute(
            'SELECT total_days, used_days FROM leave_balances WHERE user_id = ? AND leave_type_id = ? AND year = YEAR(CURRENT_DATE)',
            [userId, requestRows[0].leave_type_id]
        );

        if (balanceRows.length === 0) {
            return res.status(400).json({ message: 'No leave balance found for this type' });
        }

        const balance = balanceRows[0];
        const remainingDays = balance.total_days - balance.used_days;

        if (requestedDays > remainingDays) {
            return res.status(400).json({
                message: 'Insufficient sick leave balance',
                requested: requestedDays,
                remaining: remainingDays
            });
        }

        // Update the leave request using the existing medical_certificate field
        await connection.execute(
            `UPDATE leave_requests 
            SET start_date = ?, end_date = ?, status = ?, 
            medical_certificate = ?, is_full_day = ?,
            start_time = ?, end_time = ?, updated_at = NOW()
            WHERE request_id = ?`,
            [
                start_date,
                end_date,
                'pending',
                medical_certificate,
                is_full_day ? 1 : 0,
                start_time || null,
                end_time || null,
                requestId
            ]
        );

        // Update leave balance
        await connection.execute(
            'UPDATE leave_balances SET used_days = used_days + ? WHERE user_id = ? AND leave_type_id = ? AND year = YEAR(CURRENT_DATE)',
            [requestedDays, userId, requestRows[0].leave_type_id]
        );

        await connection.commit();

        res.json({
            success: true,
            message: 'Sick leave request completed successfully',
            days_requested: requestedDays
        });
    } catch (error) {
        await connection.rollback();
        console.error('Complete sick leave request error:', error);
        res.status(500).json({ error: 'Internal server error', details: error.message });
    } finally {
        connection.release();
    }
});

// Get Pending Certificate Requests
app.get('/api/leave/pending-certificates/:userId', authenticateToken, async (req, res) => {
    try {
        const userId = req.params.userId;

        // Ensure the requesting user is only accessing their own data
        if (req.user.userId != userId) {
            return res.status(403).json({ message: 'Unauthorized access to user data' });
        }

        // Get all leave requests with "Pending Certificate" status for this user
        const [rows] = await db.execute(
            `SELECT lr.request_id, lt.leave_type_name as leave_type, 
            lr.reason, lr.status, lr.created_at, lr.request_date 
            FROM leave_requests lr 
            JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id 
            WHERE lr.user_id = ? AND lr.status = 'Pending Certificate' 
            ORDER BY lr.created_at DESC`,
            [userId]
        );

        res.json({
            success: true,
            pendingRequests: rows
        });
    } catch (error) {
        console.error('Error fetching pending certificate requests:', error);
        res.status(500).json({
            success: false,
            error: 'Internal server error',
            details: error.message
        });
    }
});

// Cancel Leave Request Route
app.put('/api/leave/cancel/:requestId', authenticateToken, async (req, res) => {
    const connection = await db.getConnection();

    try {
        await connection.beginTransaction();

        const requestId = req.params.requestId;
        const userId = req.user.userId;

        // Verify the request belongs to the user and is cancelable
        const [requestRows] = await connection.execute(
            `SELECT request_id, user_id, leave_type_id, start_date, end_date, status 
       FROM leave_requests 
       WHERE request_id = ?`,
            [requestId]
        );

        if (requestRows.length === 0) {
            return res.status(404).json({ message: 'Leave request not found' });
        }

        const leaveRequest = requestRows[0];

        if (leaveRequest.user_id != userId) {
            return res.status(403).json({ message: 'Unauthorized access to this leave request' });
        }

        // Check if the request is cancelable (future date and correct status)
        const startDate = new Date(leaveRequest.start_date);
        const today = new Date();
        today.setHours(0, 0, 0, 0); // Set to beginning of today

        const isPastDate = startDate < today;
        const status = leaveRequest.status.toLowerCase();

        if (isPastDate) {
            return res.status(400).json({
                message: 'Cannot cancel a leave request that has already started or passed'
            });
        }

        if (status !== 'pending' && status !== 'approved') {
            return res.status(400).json({
                message: 'Only pending or approved leave requests can be cancelled'
            });
        }

        // Calculate the number of days to return to balance
        const endDate = new Date(leaveRequest.end_date);
        let workingDays = 0;

        for (let d = new Date(startDate); d <= endDate; d.setDate(d.getDate() + 1)) {
            // Skip weekends (0 = Sunday, 6 = Saturday)
            if (d.getDay() !== 0 && d.getDay() !== 6) {
                workingDays++;
            }
        }

        // Update leave request status to "cancelled"
        await connection.execute(
            'UPDATE leave_requests SET status = ?, updated_at = NOW() WHERE request_id = ?',
            ['cancelled', requestId]
        );

        // Return the days to the leave balance
        await connection.execute(
            `UPDATE leave_balances 
       SET used_days = GREATEST(0, used_days - ?) 
       WHERE user_id = ? AND leave_type_id = ? AND year = YEAR(CURRENT_DATE)`,
            [workingDays, userId, leaveRequest.leave_type_id]
        );

        await connection.commit();

        res.json({
            success: true,
            message: 'Leave request cancelled successfully',
            days_returned: workingDays
        });
    } catch (error) {
        await connection.rollback();
        console.error('Error cancelling leave request:', error);
        res.status(500).json({ error: 'Internal server error', details: error.message });
    } finally {
        connection.release();
    }
});

app.put('/api/leave/:requestId', authenticateToken, async (req, res) => {
    const connection = await db.getConnection();

    try {
        await connection.beginTransaction();

        const requestId = req.params.requestId;
        const userId = req.user.userId;
        const { leave_type, start_date, end_date, reason, is_full_day, start_time, end_time } = req.body;

        // Validate required fields
        if (!leave_type || !start_date || !end_date) {
            return res.status(400).json({ message: 'Missing required fields' });
        }

        // Map leave type name to ID using your existing mapping
        const LEAVE_TYPES = {
            'Annual': 1,
            'Sick': 2,
            'Personal': 3
        };

        const leave_type_id = LEAVE_TYPES[leave_type];
        if (!leave_type_id) {
            return res.status(400).json({ message: 'Invalid leave type' });
        }

        // Verify the request belongs to the user
        const [requestRows] = await connection.execute(
            'SELECT request_id, user_id, leave_type_id, start_date, end_date, is_full_day, status FROM leave_requests WHERE request_id = ?',
            [requestId]
        );

        if (requestRows.length === 0) {
            return res.status(404).json({ message: 'Leave request not found' });
        }

        if (requestRows[0].user_id != userId) {
            return res.status(403).json({ message: 'Unauthorized access to this leave request' });
        }

        const oldRequest = requestRows[0];

        // Check if request is editable based on status and date
        const startDate = new Date(oldRequest.start_date);
        const today = new Date();
        today.setHours(0, 0, 0, 0); // Set to beginning of today
        const isPastDate = startDate < today;
        const status = oldRequest.status.toLowerCase();

        // Only pending requests or future approved requests can be edited
        const isEditable = status === 'pending' || (status === 'approved' && !isPastDate);

        if (!isEditable) {
            return res.status(400).json({
                message: 'This leave request cannot be edited due to its status or date'
            });
        }

        // Calculate days for old request
        const oldStartDate = new Date(oldRequest.start_date);
        const oldEndDate = new Date(oldRequest.end_date);
        let oldRequestedDays = 0;
        for (let d = new Date(oldStartDate); d <= oldEndDate; d.setDate(d.getDate() + 1)) {
            if (d.getDay() !== 0 && d.getDay() !== 6) {
                oldRequestedDays += oldRequest.is_full_day ? 1 : 0.5;
            }
        }

        // Calculate days for new request
        const newStartDate = new Date(start_date);
        const newEndDate = new Date(end_date);
        let newRequestedDays = 0;
        for (let d = new Date(newStartDate); d <= newEndDate; d.setDate(d.getDate() + 1)) {
            if (d.getDay() !== 0 && d.getDay() !== 6) {
                newRequestedDays += is_full_day ? 1 : 0.5;
            }
        }

        // Calculate the difference in days
        const daysDifference = newRequestedDays - oldRequestedDays;

        // If leave type is changing or days are increasing, check balance
        if (oldRequest.leave_type_id !== leave_type_id || daysDifference > 0) {
            // Check if user has enough leave balance
            const [balanceRows] = await connection.execute(
                'SELECT total_days, used_days FROM leave_balances WHERE user_id = ? AND leave_type_id = ? AND year = YEAR(CURRENT_DATE)',
                [userId, leave_type_id]
            );

            if (balanceRows.length === 0) {
                return res.status(400).json({ message: 'No leave balance found for this type' });
            }

            const balance = balanceRows[0];
            const remainingDays = balance.total_days - balance.used_days;

            // If leave type is changing, we need the full new amount
            // If same type, we only need the difference
            const daysNeeded = oldRequest.leave_type_id !== leave_type_id ?
                newRequestedDays : daysDifference;

            if (daysNeeded > remainingDays) {
                return res.status(400).json({
                    message: 'Insufficient leave balance',
                    requested: daysNeeded,
                    remaining: remainingDays
                });
            }
        }

        // Determine the new status - if the request was approved and is being modified, set back to pending
        let newStatus = oldRequest.status;
        if (status === 'approved') {
            newStatus = 'pending';
            console.log('Setting approved leave request back to pending after modification');
        }

        // If leave type is changing, update both balances
        if (oldRequest.leave_type_id !== leave_type_id) {
            // Return days to old leave type
            await connection.execute(
                'UPDATE leave_balances SET used_days = GREATEST(0, used_days - ?) WHERE user_id = ? AND leave_type_id = ? AND year = YEAR(CURRENT_DATE)',
                [oldRequestedDays, userId, oldRequest.leave_type_id]
            );

            // Take days from new leave type
            await connection.execute(
                'UPDATE leave_balances SET used_days = used_days + ? WHERE user_id = ? AND leave_type_id = ? AND year = YEAR(CURRENT_DATE)',
                [newRequestedDays, userId, leave_type_id]
            );
        }
        // If same leave type but different days
        else if (daysDifference !== 0) {
            // Update the balance (can be positive or negative adjustment)
            await connection.execute(
                'UPDATE leave_balances SET used_days = GREATEST(0, used_days + ?) WHERE user_id = ? AND leave_type_id = ? AND year = YEAR(CURRENT_DATE)',
                [daysDifference, userId, leave_type_id]
            );
        }

        // Update the leave request with new status
        await connection.execute(
            `UPDATE leave_requests 
            SET leave_type_id = ?, start_date = ?, end_date = ?, reason = ?,
            is_full_day = ?, start_time = ?, end_time = ?, status = ?, updated_at = NOW()
            WHERE request_id = ?`,
            [
                leave_type_id,
                start_date,
                end_date,
                reason || null,
                is_full_day ? 1 : 0,
                start_time || null,
                end_time || null,
                newStatus,
                requestId
            ]
        );

        await connection.commit();

        res.json({
            success: true,
            message: 'Leave request updated successfully',
            days_requested: newRequestedDays,
            days_difference: daysDifference,
            status: newStatus
        });
    } catch (error) {
        await connection.rollback();
        console.error('Update leave request error:', error);
        res.status(500).json({ error: 'Internal server error', details: error.message });
    } finally {
        connection.release();
    }
});

// Get Leave Balance Route
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

// Get Leave History Route
app.get('/api/leave-requests/:userId', authenticateToken, async (req, res) => {
    try {
        const userId = req.params.userId;

        const [rows] = await db.execute(
            'SELECT lr.request_id, lt.leave_type_name as leave_type, lr.start_date, lr.end_date, lr.reason, lr.status, lr.created_at, lr.medical_certificate FROM leave_requests lr JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id WHERE lr.user_id = ? ORDER BY lr.created_at DESC',
            [userId]
        );

        res.json(rows);
    } catch (error) {
        console.error('Leave requests error: ', error);
        res.status(500).json({ error: 'Internal server error' });
    }
});

// Admin Update Leave Request Status Route
app.put('/api/admin/update-leave-status', authenticateToken, async (req, res) => {
    const connection = await db.getConnection();

    try {
        await connection.beginTransaction();

        const { request_id, status, admin_id, reason } = req.body;

        // Validate required fields
        if (!request_id || !status) {
            return res.status(400).json({ message: 'Missing required fields' });
        }

        // Validate status value
        if (!['approved', 'rejected', 'pending'].includes(status)) {
            return res.status(400).json({ message: 'Invalid status value' });
        }

        // Get the leave request details before updating
        const [leaveRows] = await connection.execute(
            'SELECT user_id, leave_type_id, start_date, end_date, status FROM leave_requests WHERE request_id = ?',
            [request_id]
        );

        if (leaveRows.length === 0) {
            return res.status(404).json({ message: 'Leave request not found' });
        }

        const leaveRequest = leaveRows[0];

        // If the status is not changing, no need to do anything
        if (leaveRequest.status === status) {
            await connection.commit();
            return res.json({
                message: 'Status already set to ' + status
            });
        }

        // If we're rejecting a leave request, we need to update the leave balance
        if (status === 'rejected') {
            // Calculate the number of working days in the leave period
            const startDate = new Date(leaveRequest.start_date);
            const endDate = new Date(leaveRequest.end_date);

            let workingDays = 0;
            for (let d = new Date(startDate); d <= endDate; d.setDate(d.getDate() + 1)) {
                // Skip weekends (0 = Sunday, 6 = Saturday)
                if (d.getDay() !== 0 && d.getDay() !== 6) {
                    workingDays++;
                }
            }

            // Get current balance to avoid potential negative values
            const [balanceRows] = await connection.execute(
                'SELECT used_days FROM leave_balances WHERE user_id = ? AND leave_type_id = ? AND year = YEAR(CURRENT_DATE)',
                [leaveRequest.user_id, leaveRequest.leave_type_id]
            );

            if (balanceRows.length > 0) {
                // Update leave balance by decreasing the used days
                await connection.execute(
                    `UPDATE leave_balances 
                    SET used_days = used_days - ? 
                    WHERE user_id = ? AND leave_type_id = ? AND year = YEAR(CURRENT_DATE)`,
                    [workingDays, leaveRequest.user_id, leaveRequest.leave_type_id]
                );
            }
        }

        // Update the leave request status and admin notes
        await connection.execute(
            'UPDATE leave_requests SET status = ?, admin_notes = ?, updated_at = NOW() WHERE request_id = ?',
            [status, reason || null, request_id]
        );

        await connection.commit();

        res.json({
            message: 'Leave request status updated successfully'
        });
    } catch (error) {
        await connection.rollback();
        console.error('Error updating leave status:', error);
        res.status(500).json({ error: 'Internal server error', details: error.message });
    } finally {
        connection.release();
    }
});

// Get All Leave Requests (Admin)
app.get('/api/admin/leave-requests', authenticateToken, async (req, res) => {
    try {
        const [rows] = await db.execute(
            `SELECT lr.request_id, lr.user_id, lr.leave_type_id, lt.leave_type_name as leave_type,
            lr.start_date, lr.end_date, lr.reason, lr.status, lr.created_at, lr.medical_certificate,
            ui.user_name, ui.user_surname
            FROM leave_requests lr
            JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
            JOIN user_information ui ON lr.user_id = ui.user_id
            ORDER BY lr.created_at DESC`
        );

        // Add employee name to each record
        const formattedRows = rows.map(row => {
            return {
                ...row,
                employee_name: `${row.user_name} ${row.user_surname}`,
            };
        });

        res.json(formattedRows);
    } catch (error) {
        console.error('Error fetching leave requests:', error);
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

// Attendance Status Route
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

            res.json({
                lastPunchType: lastPunch.punch_type,
                lastPunchDate: lastPunch.punch_date,
                lastPunchTime: lastPunch.punch_time,
                lastPhotoUrl: lastPunch.photo_url
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

// Attendance History Route
app.get('/api/attendance-history/:userId', authenticateToken, async (req, res) => {
    try {
        const userId = req.params.userId;

        const [rows] = await db.execute(
            'SELECT * FROM log_Information WHERE user_id = ? ORDER BY date_time_event DESC',
            [userId]
        );

        res.json(rows);
    } catch (error) {
        console.error('Attendance history error:', error);
        res.status(500).json({ error: 'Internal server error' });
    }
});


// Login Route
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
        }

        // Modified query to join with departments and roles tables
        const [rows] = await db.execute(
            `SELECT l.user_login_id, l.email, l.user_login_pass, l.user_id, l.mfa_enabled,
            ui.user_name, ui.user_surname, ui.user_email, ui.user_phone,
            ui.department_id, ui.role_id,
            d.department, r.role
            FROM login l 
            JOIN user_information ui ON l.user_id = ui.user_id
            JOIN departments d ON ui.department_id = d.department_id
            JOIN roles r ON ui.role_id = r.role_id
            WHERE l.email = ?`,
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
            // Verify password
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

                // Response with user details
                const response = {
                    token: token,
                    user: {
                        id: user.user_id,
                        email: user.user_email,
                        full_name: `${user.user_name} ${user.user_surname}`,
                        department: user.department,
                        department_id: user.department_id,
                        role: user.role,
                        role_id: user.role_id,
                        phone: user.user_phone,
                        mfa_enabled: user.mfa_enabled === 1
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

// Serve uploaded files
app.use('/uploads', express.static('uploads'));

// Serve profile pictures
app.use('/profile-pictures', 
    express.static('/home/softwaredev/profile_pictures')
);

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

// Enable garbage collection if possible
try {
    if (global.gc) {
        console.log('Garbage collection is available and will be used');
    } else {
        console.log('Garbage collection is not available. Start with --expose-gc flag for better memory management');
    }
} catch (e) {
    console.log('Garbage collection is not available. Start with --expose-gc flag for better memory management');
    global.gc = () => { console.log('GC not available'); };
}

// Configure memory monitoring
const memoryMonitoringInterval = 10 * 60 * 1000; // 10 minutes
setInterval(() => {
    const memoryUsage = process.memoryUsage();
    console.log('Memory usage:');
    console.log(`  RSS: ${Math.round(memoryUsage.rss / 1024 / 1024)} MB`);
    console.log(`  Heap Total: ${Math.round(memoryUsage.heapTotal / 1024 / 1024)} MB`);
    console.log(`  Heap Used: ${Math.round(memoryUsage.heapUsed / 1024 / 1024)} MB`);
    console.log(`  External: ${Math.round(memoryUsage.external / 1024 / 1024)} MB`);

    // Try to perform garbage collection if heap is over 70% of total
    if (global.gc && (memoryUsage.heapUsed / memoryUsage.heapTotal > 0.7)) {
        console.log('Triggering garbage collection due to high memory usage');
        global.gc();
    }
}, memoryMonitoringInterval);

// Server Initialization
const PORT = process.env.PORT || 3000;
const server = app.listen(PORT, '127.0.0.1', () => {
  console.log(`HTTP Server listening on http://127.0.0.1:${PORT}`);
});

// Graceful Shutdown Handler
process.on('SIGTERM', () => {
    console.log('SIGTERM signal received: closing HTTP server');
    server.close(() => {
        console.log('HTTP server closed');
        // Close database connections if needed
        process.exit(0);
    });
});

// Handle uncaught exceptions
process.on('uncaughtException', (error) => {
    console.error('Uncaught Exception:', error);
    // Attempt graceful shutdown
    server.close(() => {
        process.exit(1);
    });
});

module.exports = {
    app,
    server
};
