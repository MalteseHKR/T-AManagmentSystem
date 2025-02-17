# Time Attendance App Backend

This is the backend for the Time Attendance App, built using Laravel. The backend serves as the API for the mobile application, handling authentication, data management, and communication with the database.

## Project Structure

- **app/**: Contains the core application logic, including models, controllers, and middleware.
- **bootstrap/**: Contains files for bootstrapping the Laravel application.
- **config/**: Contains configuration files for various services and settings.
- **database/**: Contains database migrations, seeders, and factories.
- **public/**: Contains the public assets of the application.
- **resources/**: Contains views, raw assets, and language files.
- **routes/**: Contains the route definitions for the application.
- **storage/**: Contains logs, compiled views, and other generated files.
- **tests/**: Contains the test cases for the application.

## Setup Instructions

### Backend (Laravel)

1. **Install Composer**: Ensure Composer is installed on your machine.
2. **Navigate to the Backend Directory**: 
   ```bash
   cd backend
   ```
3. **Install Dependencies**: 
   ```bash
   composer install
   ```
4. **Configure Environment Variables**: 
   - Copy the example environment file:
     ```bash
     cp .env.example .env
     ```
   - Update the `.env` file with your database credentials:
     ```
     DB_CONNECTION=mysql
     DB_HOST=192.168.1.5
     DB_PORT=3306
     DB_DATABASE=garrison_records
     DB_USERNAME=application
     DB_PASSWORD=Xfxgtx295!!
     ```
5. **Generate Application Key**: 
   ```bash
   php artisan key:generate
   ```
6. **Run Migrations**: 
   ```bash
   php artisan migrate
   ```
7. **Start the Laravel Server**: 
   ```bash
   php artisan serve
   ```

### Frontend (React)

1. **Install Node.js and npm**: Ensure Node.js and npm are installed on your machine.
2. **Navigate to the Frontend Directory**: 
   ```bash
   cd frontend
   ```
3. **Install Dependencies**: 
   ```bash
   npm install
   ```
4. **Configure Environment Variables**: 
   - Copy the example environment file:
     ```bash
     cp .env.example .env
     ```
   - Update the `.env` file with any necessary variables.
5. **Start the React Application**: 
   ```bash
   npm start
   ```

## Dependencies

- **Backend**: Laravel framework, MySQL database driver.
- **Frontend**: React, React Router, Axios (for API calls), and any other libraries you may need.

## License

This project is licensed under the MIT License. See the LICENSE file for details.