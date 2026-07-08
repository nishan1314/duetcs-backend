# Admin Panel Backend API Documentation

## Overview

This backend provides a complete admin panel system for the DUET Computer Society website with role-based access control, user management, content management, and more.

## Database Setup

### 1. Run the Schema Files

Execute the SQL files in this order:

```bash
# Main database schema (if not already created)
mysql -u your_username -p < database/schema.sql

# Admin schema with roles, permissions, and content tables
mysql -u your_username -p < database/admin_schema.sql
```

### 2. Database Tables Created

- `admin_roles` - Role definitions (Super Admin, Admin, Moderator, etc.)
- `admin_permissions` - Permission definitions
- `role_permissions` - Role-permission mappings
- `user_roles` - User-role assignments
- `payment_records` - Payment tracking
- `notices` - Notice/announcement management
- `events` - Event management
- `event_details` - Event details and gallery
- `news` - News articles
- `achievements` - Achievement records
- `executive_members` - Executive board members
- `wings` - Club divisions/wings
- `website_content` - Dynamic website content (Hero, About, etc.)
- `gallery_images` - Gallery image management

## API Endpoints

### Authentication APIs

#### Admin Login

```
POST /api/admin/login.php
Body: {
  "email": "admin@duet.ac.bd",
  "password": "password"
}
Response: {
  "success": true,
  "user": { ...user data with roles and permissions... }
}
```

#### Get Current Admin User

```
GET /api/admin/user.php
Response: {
  "success": true,
  "user": { ...user data with roles and permissions... }
}
```

#### Admin Logout

```
POST /api/admin/logout.php
Response: {
  "success": true,
  "message": "Logout successful"
}
```

### User Management APIs

#### List Users

```
GET /api/admin/users.php?search=&status=all&role=all&page=1&limit=20
Response: {
  "success": true,
  "data": [...users...],
  "pagination": { total, page, limit, pages }
}
```

#### Create User

```
POST /api/admin/users.php
Body: {
  "full_name": "John Doe",
  "email": "john@duet.ac.bd",
  "student_id": "CSE-123",
  "department": "CSE",
  "year_semester": "3-1",
  "password": "password123"
}
```

#### Update User

```
PUT /api/admin/users.php
Body: {
  "id": 1,
  "full_name": "John Updated",
  "is_verified": true
}
```

#### Delete User

```
DELETE /api/admin/users.php
Body: { "id": 1 }
```

### Roles & Privileges APIs

#### Get All Roles with Permissions

```
GET /api/admin/roles.php
Response: {
  "success": true,
  "roles": [...roles with permissions...]
}
```

#### Get All Permissions

```
GET /api/admin/roles.php?action=permissions
Response: {
  "success": true,
  "permissions": [...permissions...],
  "grouped": { ...permissions grouped by module... }
}
```

#### Assign Role to User

```
POST /api/admin/roles.php?action=assign
Body: {
  "user_id": 1,
  "role_id": 2
}
```

#### Revoke Role from User

```
POST /api/admin/roles.php?action=revoke
Body: {
  "user_id": 1,
  "role_id": 2
}
```

#### Update Role Permissions

```
PUT /api/admin/roles.php?action=role-permissions
Body: {
  "role_id": 3,
  "permission_ids": [1, 2, 3, 4]
}
```

### Payment Management APIs

#### List Payments

```
GET /api/admin/payments.php?search=&status=all&type=all&page=1&limit=20
```

#### Create Payment

```
POST /api/admin/payments.php
Body: {
  "user_id": 1,
  "amount": 500.00,
  "payment_method": "bKash",
  "payment_type": "membership",
  "transaction_id": "TXN123",
  "payment_status": "pending"
}
```

#### Update Payment

```
PUT /api/admin/payments.php
Body: {
  "id": 1,
  "payment_status": "completed"
}
```

#### Verify Payment

```
POST /api/admin/payments.php?action=verify
Body: { "id": 1 }
```

#### Get Payment Statistics

```
GET /api/admin/payments.php?action=stats
Response: {
  "success": true,
  "stats": {
    "total": { count, amount },
    "pending": { count, amount },
    "completed": { count, amount },
    "byType": { ... },
    "recent": [ ... ]
  }
}
```

### Notice Management APIs

#### List Notices

```
GET /api/admin/notices.php?search=&status=all&priority=all&page=1&limit=20
```

#### Create Notice

```
POST /api/admin/notices.php
Body: {
  "title": "Important Notice",
  "description": "Notice details",
  "type": "message",
  "priority": "high",
  "status": "active"
}
```

#### Update Notice

```
PUT /api/admin/notices.php
Body: {
  "id": 1,
  "title": "Updated Notice",
  "status": "archived"
}
```

#### Delete Notice

```
DELETE /api/admin/notices.php
Body: { "id": 1 }
```

### Events Content APIs

#### List Events

```
GET /api/admin/content/events.php?search=&status=all&category=all
```

#### Create Event

```
POST /api/admin/content/events.php
Body: {
  "title": "Programming Contest 2025",
  "description": "Annual programming competition",
  "event_date": "2025-03-15",
  "event_time": "10:00",
  "venue": "CSE Building",
  "category": "Competition",
  "status": "upcoming",
  "image": "/img/events/contest.jpg",
  "registrationLink": "https://..."
}
```

#### Update Event

```
PUT /api/admin/content/events.php
Body: {
  "id": 1,
  "title": "Updated Event",
  "status": "ongoing"
}
```

#### Delete Event

```
DELETE /api/admin/content/events.php
Body: { "id": 1 }
```

### News Content APIs

#### List News

```
GET /api/admin/content/news.php?search=&status=all&category=all
```

#### Create News

```
POST /api/admin/content/news.php
Body: {
  "title": "News Title",
  "slug": "news-title",
  "description": "Short description",
  "content": "Full content...",
  "category": "Technology",
  "status": "published",
  "image": "/img/news/news1.jpg"
}
```

#### Update/Delete News

Similar to Events API

### Wings Management APIs

#### List Wings

```
GET /api/admin/content/wings.php
```

#### Create Wing

```
POST /api/admin/content/wings.php
Body: {
  "name": "Competitive Programming",
  "description": "Focus on algorithmic problem solving",
  "icon": "code",
  "color": "#3B82F6",
  "displayOrder": 1
}
```

#### Update/Delete Wings

Similar pattern to other content APIs

### Achievements Management APIs

#### List Achievements

```
GET /api/admin/content/achievements.php?category=all
```

#### Create Achievement

```
POST /api/admin/content/achievements.php
Body: {
  "title": "ICPC World Finals",
  "description": "Team qualified for world finals",
  "date": "2025-05-15",
  "category": "Competition",
  "isFeatured": true
}
```

### Executive Board Management APIs

#### List Executive Members

```
GET /api/admin/content/executive.php?term=2025&active=true
```

#### Create Executive Member

```
POST /api/admin/content/executive.php
Body: {
  "name": "John Doe",
  "position": "President",
  "userId": 5,
  "email": "john@duet.ac.bd",
  "linkedin": "https://linkedin.com/in/...",
  "github": "https://github.com/...",
  "termYear": "2025",
  "displayOrder": 1
}
```

### Website Content Management APIs

#### Get Section Content

```
GET /api/admin/content/website.php?section=hero
Sections: hero, about, features, legacy

Response: {
  "success": true,
  "data": { ...section content as JSON... }
}
```

#### Update Section Content

```
PUT /api/admin/content/website.php?section=hero
Body: {
  "content": {
    "title": "Welcome to DUET CS",
    "subtitle": "Building Tomorrow's Leaders",
    "backgroundImage": "/img/hero.jpg",
    ...
  }
}
```

### Gallery Management APIs

#### List Gallery Images

```
GET /api/admin/content/gallery.php?category=all&event_id=1
```

#### Create Gallery Item

```
POST /api/admin/content/gallery.php
Body: {
  "title": "Image Title",
  "description": "Image description",
  "imageUrl": "/img/gallery/img1.jpg",
  "category": "Events",
  "eventId": 1,
  "isFeatured": true
}
```

## Permission System

### Permission Keys

#### User Management

- `users.view` - View users
- `users.create` - Create users
- `users.edit` - Edit users
- `users.delete` - Delete users
- `users.verify` - Verify user accounts

#### Role Management

- `roles.view` - View roles
- `roles.manage` - Create/edit roles
- `roles.assign` - Assign roles to users

#### Payment Management

- `payments.view` - View payments
- `payments.verify` - Verify payments
- `payments.manage` - Full payment management

#### Notice Management

- `notices.view` - View notices
- `notices.create` - Create notices
- `notices.edit` - Edit notices
- `notices.delete` - Delete notices

#### Event Management

- `events.view` - View events
- `events.create` - Create events
- `events.edit` - Edit events
- `events.delete` - Delete events

#### News Management

- `news.view` - View news
- `news.create` - Create news
- `news.edit` - Edit news
- `news.delete` - Delete news
- `news.publish` - Publish news

#### Content Management

- `content.view` - View website content
- `content.edit` - Edit website content
- `executive.manage` - Manage executive board
- `wings.manage` - Manage wings
- `gallery.manage` - Manage gallery
- `achievements.manage` - Manage achievements

## Default Roles

1. **Super Admin** - Full system access (all permissions)
2. **Admin** - General administrative access
3. **Moderator** - Content moderation and user management
4. **Event Manager** - Manage events and related content
5. **Content Manager** - Manage website content and news
6. **Member** - Basic user role with limited access

## Security Features

1. **Session-based Authentication** - Secure PHP sessions
2. **Role-based Access Control (RBAC)** - Granular permission system
3. **Password Hashing** - bcrypt password hashing
4. **CSRF Protection** - Via session validation
5. **Input Validation** - All inputs sanitized and validated
6. **SQL Injection Prevention** - Prepared statements throughout

## Usage in Frontend

### Example: Checking Permissions

```javascript
// In frontend, after login
const user = await adminApi.getCurrentUser();

// Check if user has permission
if (user.permissions.some((p) => p.permission_key === "users.create")) {
  // Show create user button
}

// Check if user is admin
if (user.is_admin) {
  // Show admin dashboard
}
```

### Example: API Call

```javascript
// List users
const response = await fetch("http://your-api-url/api/admin/users.php", {
  credentials: "include", // Important for session cookies
  headers: {
    "Content-Type": "application/json",
  },
});

const data = await response.json();
```

## Setup Instructions

1. **Import Database Schema**

   ```bash
   mysql -u root -p duetcs_db < backend/database/admin_schema.sql
   ```

2. **Configure CORS**
   Update `backend/config/cors.php` with your frontend URL

3. **Create First Admin User**

   - Register a normal user via the regular registration flow
   - Manually insert a Super Admin role assignment in the database:

   ```sql
   INSERT INTO user_roles (user_id, role_id, assigned_by)
   VALUES (1, (SELECT id FROM admin_roles WHERE role_name = 'Super Admin'), 1);
   ```

4. **Test Admin Login**
   - Use the admin login endpoint with the user's credentials
   - Verify roles and permissions are returned

## File Structure

```
backend/
├── api/
│   └── admin/
│       ├── login.php
│       ├── user.php
│       ├── logout.php
│       ├── users.php
│       ├── roles.php
│       ├── payments.php
│       ├── notices.php
│       └── content/
│           ├── events.php
│           ├── news.php
│           ├── wings.php
│           ├── achievements.php
│           ├── executive.php
│           ├── website.php
│           └── gallery.php
├── database/
│   ├── schema.sql
│   └── admin_schema.sql
├── utils/
│   └── admin-auth.php
└── config/
    ├── database.php
    └── cors.php
```

## Notes

- All APIs require authentication (active session)
- Admin APIs require admin role
- Specific operations require specific permissions
- All responses follow the same format: `{ success: boolean, message?: string, data?: any }`
- Pagination is available on list endpoints
- Search and filtering supported on most list endpoints

## Error Codes

- `400` - Bad Request (missing/invalid parameters)
- `401` - Unauthorized (not authenticated)
- `403` - Forbidden (insufficient permissions)
- `404` - Not Found
- `405` - Method Not Allowed
- `500` - Internal Server Error

## Support

For issues or questions, contact the development team.
