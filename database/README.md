# Database Setup and Migration

## Initial Setup

### Step 1: Create Database and Core Tables

Run the `schema.sql` file to create the database and tables:

```sql
mysql -u root -p < schema.sql
```

Or in MySQL:

```sql
mysql -u root
source schema.sql
```

### Step 2: Setup Admin Schema

Run the `admin_schema.sql` file to create admin-related tables:

```sql
mysql -u root -p duetcs_db < admin_schema.sql
```

Or in MySQL:

```sql
USE duetcs_db;
source admin_schema.sql
```

### Step 3: Create Sample Data (Optional - for testing/development)

Run `sample-content.sql` to insert test data:

```sql
mysql -u root -p duetcs_db < sample-content.sql
```

Or in MySQL:

```sql
USE duetcs_db;
source sample-content.sql
```

This will populate:

- Sample events
- Sample news articles
- Sample achievements
- Sample executive members
- Sample gallery images
- Sample website content sections
- Sample wings/divisions

### Step 4: Setup Notices Table

Run `create_notices_table.sql`:

```sql
mysql -u root -p duetcs_db < create_notices_table.sql
```

### Step 5: Setup Coder Handles (Optional)

Run `coder_handles.sql` if needed:

```sql
mysql -u root -p duetcs_db < coder_handles.sql
```

## Migrations

### Adding Profile Image Support

If you already have an existing database, run this migration to add the `profile_image` column:

```sql
mysql -u root -p duetcs_db < add_profile_image.sql
```

Or run it directly in MySQL:

```sql
USE duetcs_db;
ALTER TABLE users ADD COLUMN profile_image LONGTEXT NULL AFTER password;
```

### Creating Admin User

To create an admin user, run `create_admin_user.sql`:

```sql
mysql -u root -p duetcs_db < create_admin_user.sql
```

This will create test admin user if needed.

## Database Structure

### Core Tables (schema.sql)

- `users` - User accounts and authentication
- `email_verifications` - Email verification tokens
- `login_sessions` - User login sessions
- `coder_handles` - Coder profile handles

### Admin Tables (admin_schema.sql)

- `admin_roles` - Role definitions
- `admin_permissions` - Permission definitions
- `role_permissions` - Role-permission mappings
- `user_roles` - User-role assignments
- `notices` - Admin notices
- `payment_records` - Payment transactions

### Content Tables (sample-content.sql)

- `events` - Events and competitions
- `event_details` - Event details/gallery
- `news` - News articles
- `achievements` - Society achievements
- `executive_members` - Executive board
- `gallery_images` - Gallery images
- `website_content` - Website content sections
- `wings` - Wings/divisions

## Notes

- The `profile_image` column stores images as base64-encoded strings (LONGTEXT type)
- The column is nullable, so existing users won't be affected
- Profile images are optional during registration
- All tables use `utf8mb4` charset for Unicode support
- Foreign keys are properly configured for data integrity
- Timestamps are automatically managed (created_at, updated_at)
