# Hospital/Clinic Logo Feature Setup

This document explains how to set up the hospital/clinic logo feature for the prescription system.

## Database Setup

### For New Installations

Run the following SQL script to create the required table:

```sql
-- File: create_clinic_logos_table.sql
-- This creates the clinic_logos table for storing hospital/clinic logos

CREATE TABLE IF NOT EXISTS `clinic_logos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` varchar(50) NOT NULL,
  `logo_path` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `uploaded_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_client_active` (`client_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### For Existing Installations (Migration)

If you already have the `clinic_logos` table from a previous version, run the migration script:

```bash
mysql -u [username] -p [database_name] < update_clinic_logos_table.sql
```

This will:
- Remove the unique constraint on `client_id` (allows multiple logos per client)
- Add the `is_active` column to track which logo is currently active
- Set existing logos as active
- Add performance indexes

## File System Setup

Create the uploads directory for storing logos:

```bash
mkdir -p /var/www/html/doctor_app/uploads/logos
chmod 777 /var/www/html/doctor_app/uploads/logos
```

Or if you're in the project root:

```bash
mkdir -p uploads/logos
chmod 777 uploads/logos
```

## Features Added

### 1. Print Prescription Page (`/add_prescription/print.html`)
- Logo now displays in the top-right corner beside doctor information
- Only the active logo is displayed
- Logo loads automatically when prescription is printed
- Responsive design - logo adjusts on mobile devices

### 2. Dashboard Card (`/dashboard/dashboard.html`)
- New "Add Hospital Logo" card added to dashboard
- Links to the logo management page

### 3. Logo Management Page (`/dashboard/manage_logo.html`)
- **Upload**: Upload multiple logos (PNG, JPG, GIF) - up to 2MB each
- **Table View**: Shows all uploaded logos with previews
- **Apply**: Set any logo as active (appears on prescriptions)
- **Delete**: Remove individual logos
- **Status Badge**: Shows which logo is currently active
- Real-time table updates after each action

### 4. Backend API (`/dashboard/dashboard.php`)
- `upload_logo` - Upload a new logo (stored as inactive by default)
- `list_logos` - Retrieve all logos for a client
- `apply_logo` - Set a specific logo as active (deactivates others)
- `get_logo` - Retrieve the currently active logo
- `delete_logo` - Delete a specific logo by ID

## Usage Instructions

1. **Upload Logos:**
   - Go to Dashboard → "Add Hospital Logo"
   - Click "Upload Logo"
   - Select an image file (PNG, JPG, or GIF)
   - Logo is uploaded and appears in the table immediately

2. **Apply Logo:**
   - In the logos table, find the logo you want to use
   - Click "Apply" button in the Actions column
   - Logo status changes to "Active"
   - This logo will now appear on all prescriptions

3. **Delete Logo:**
   - Click "Delete" button in the Actions column for any logo
   - Confirm deletion
   - Logo is permanently removed from the system

4. **View Logo on Prescriptions:**
   - The active logo automatically appears on all printed prescriptions
   - Located in top-right corner beside doctor details
   - Only one logo can be active at a time

## Best Practices

- **File Format:** PNG with transparent background works best
- **File Size:** Keep under 500KB for faster loading
- **Dimensions:** 300x150 pixels or similar 2:1 aspect ratio
- **Multiple Logos:** Upload different versions and switch between them easily
- **Testing:** After applying a logo, view a prescription to verify appearance

## File Structure

```
/doctor_app/
├── uploads/
│   └── logos/
│       └── logo_[client_id]_[timestamp]_[uniqid].[ext]
├── dashboard/
│   ├── manage_logo.html (Table-based logo management interface)
│   └── dashboard.php (API handler with multiple endpoints)
├── add_prescription/
│   └── print.html (Updated with logo display)
└── database/
    ├── create_clinic_logos_table.sql (For new installations)
    ├── update_clinic_logos_table.sql (For existing installations)
    └── LOGO_FEATURE_SETUP.md (This file)
```

## Security Notes

- **Client Isolation**: Logos are stored and retrieved per client_id
- **File Validation**: Strict file type checking (only PNG, JPG, GIF allowed)
- **Size Limits**: Maximum 2MB per file prevents storage abuse
- **Unique Filenames**: Each file has a unique name (timestamp + uniqid) to prevent conflicts
- **Database Integrity**: Foreign key relationships ensure data consistency
- **Access Control**: Logo operations require valid client_id from session
- **Active Status**: Only one logo can be active per client at any time
- **Orphan Prevention**: Deleting a logo removes both database entry and physical file
