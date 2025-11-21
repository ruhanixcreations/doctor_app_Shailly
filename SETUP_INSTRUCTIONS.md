# Disable/Enable Feature - Setup Instructions

## Overview
This feature allows administrators to disable/enable doctors and receptionists. When a user is disabled, they are immediately logged out from all devices.

## Database Setup

**IMPORTANT:** You must run the setup script ONCE before using this feature.

### Option 1: Run via Web Browser
1. Open your browser and navigate to:
   ```
   http://your-domain.com/setup_disable_feature.php
   ```
2. You should see confirmation messages
3. **Delete the setup_disable_feature.php file after running it**

### Option 2: Run via Command Line (if PHP CLI is available)
```bash
cd /workspace
php setup_disable_feature.php
```

## What the Setup Script Does

1. **Adds `is_disabled` column** to the `users` table
   - Type: TINYINT(1)
   - Default: 0 (enabled)

2. **Creates `user_sessions` table** to track active user sessions
   - Stores user_id and session_id mapping
   - Enables automatic logout when user is disabled

## Features Implemented

### 1. Receptionist List Page (`receptionalist_list.html`)
- ✅ Shows status badge (Active/Disabled)
- ✅ Disable button for active receptionists
- ✅ Enable button for disabled receptionists
- ✅ Confirmation dialog before disabling with warning about logout
- ✅ Immediate logout from all devices when disabled

### 2. Doctor List Page (`doctors_list.html`)
- ✅ Shows status badge (Active/Disabled)
- ✅ Disable button for active doctors
- ✅ Enable button for disabled doctors
- ✅ Confirmation dialog before disabling with warning about logout
- ✅ Immediate logout from all devices when disabled

### 3. Session Management (`check_session.php`)
- ✅ Tracks all user sessions in the database
- ✅ Automatically checks if user is disabled on each session check
- ✅ Logs out disabled users immediately
- ✅ Prevents disabled users from accessing the system

### 4. Backend API Updates
- ✅ `receptionalist_list.php` - Added `toggle_disable` action
- ✅ `doctors_list.php` - Added `toggle_disable` action
- ✅ Session cleanup when user is disabled
- ✅ Only the disabled user is logged out (no other users affected)

## How It Works

1. **Admin clicks "Disable"** on a doctor or receptionist
2. **Confirmation dialog** appears warning about immediate logout
3. **Backend processes the disable request:**
   - Updates `is_disabled = 1` in users table
   - Finds all active sessions for that user in `user_sessions` table
   - Deletes all session files for that user
   - Removes all session records from database
4. **User is immediately logged out** from all devices
5. **Next time disabled user tries to access:** 
   - `check_session.php` detects they are disabled
   - Session is destroyed
   - User is redirected to login page

## Security Notes

- ✅ Only the specific disabled user is logged out
- ✅ All other users remain logged in
- ✅ Disabled users cannot create new sessions
- ✅ Session tracking is automatic and transparent
- ✅ No passwords or sensitive data are compromised

## Testing the Feature

1. Create or use an existing receptionist/doctor account
2. Log in as that user on multiple devices/browsers
3. As admin, go to the receptionist/doctor list page
4. Click "Disable" on that user
5. Verify the user is immediately logged out on all devices
6. Try to log in as the disabled user - should be prevented
7. Click "Enable" to re-enable the user
8. User can now log in again

## Cleanup

**After running the setup script successfully, delete:**
- `setup_disable_feature.php` (security best practice)
- `SETUP_INSTRUCTIONS.md` (this file, optional)

## Troubleshooting

### If buttons don't appear:
- Clear browser cache
- Check if `is_disabled` column exists in users table

### If users aren't being logged out:
- Check if `user_sessions` table exists
- Verify session directory path in PHP files matches actual location
- Check PHP error logs for session-related errors

### If setup script fails:
- Check database credentials in the script
- Ensure user has ALTER TABLE permissions
- Check MySQL error logs
