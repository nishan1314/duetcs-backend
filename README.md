# DUET CS Backend

This folder contains the PHP backend for DUET Computer Society website.

## Database Setup

1. Start XAMPP and ensure MySQL is running
2. Open phpMyAdmin (http://localhost/phpmyadmin)
3. Import the database schema:
   - Click on "SQL" tab
   - Copy and paste the content from `database/schema.sql`
   - Click "Go" to execute

## Database Structure

### Users Table

- Stores user registration information
- Fields: id, full_name, email, student_id, department, year_semester, why_join, password, is_verified, created_at, updated_at

### Email Verifications Table

- Manages email verification tokens
- Links to users table
- Automatically expires after set time

### Login Sessions Table

- Tracks active user sessions
- Stores session tokens and metadata

### Password Resets Table

- Manages password reset functionality (for future use)

## API Endpoints

### Auth (`/api/auth/`)
- POST `register.php` - User registration
- POST `login.php` - User login
- POST `logout.php` - User logout
- POST `verify-email.php` - Email verification
- POST `resend-verification.php` - Resend verification email
- POST `forgot-password.php` - Request password reset
- POST `reset-password.php` - Reset password with token
- POST `update-password.php` - Update password (authenticated)

### User (`/api/user/`)
- GET `user.php` - Get user profile
- POST `update-profile.php` - Update profile details
- GET `check-handle.php` - Check Codeforces handle
- GET `codeforces-users.php` - Fetch Codeforces users list

