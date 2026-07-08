# Uploads Directory

This directory stores user-uploaded files.

## Structure

- `profiles/` - User profile images

## Setup

1. Ensure this directory has write permissions:

   ```bash
   chmod 755 uploads
   chmod 755 uploads/profiles
   ```

2. On Windows (XAMPP/WAMP), the permissions are usually set automatically.

## Image URLs

Images are accessible via:

```
http://localhost/duetcs-backend/uploads/profiles/[filename]
```

The database stores only the relative path: `uploads/profiles/[filename]`
