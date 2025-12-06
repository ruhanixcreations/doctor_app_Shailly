# Hospital/Clinic Logo Feature Setup

This document explains how to set up the hospital/clinic logo feature for the prescription system.

## Database Setup

Run the following SQL script to create the required table:

```sql
-- File: create_clinic_logos_table.sql
-- This creates the clinic_logos table for storing hospital/clinic logos

CREATE TABLE IF NOT EXISTS `clinic_logos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` varchar(50) NOT NULL,
  `logo_path` varchar(255) NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `client_id` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

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
- Logo loads automatically when prescription is printed
- Responsive design - logo adjusts on mobile devices

### 2. Dashboard Card (`/dashboard/dashboard.html`)
- New "Add Hospital Logo" card added to dashboard
- Links to the logo management page

### 3. Logo Management Page (`/dashboard/manage_logo.html`)
- Upload new logo (PNG, JPG, GIF)
- Preview uploaded logo
- Apply logo to save it
- Delete existing logo
- Maximum file size: 2MB
- Recommended size: 300x150 pixels

### 4. Backend API (`/dashboard/dashboard.php`)
- `upload_logo` - Upload and save logo
- `get_logo` - Retrieve current logo
- `delete_logo` - Delete logo

## Usage Instructions

1. **Upload Logo:**
   - Go to Dashboard → "Add Hospital Logo"
   - Click "Upload New Logo"
   - Select an image file (PNG, JPG, or GIF)
   - Click "Apply Logo" to save

2. **Delete Logo:**
   - Go to Logo Management page
   - Click "Delete Logo" button
   - Confirm deletion

3. **View Logo on Prescriptions:**
   - Logo will automatically appear on all printed prescriptions
   - Located in top-right corner beside doctor details

## Logo Guidelines

- **Recommended size:** 300x150 pixels or similar aspect ratio
- **Supported formats:** PNG, JPG, JPEG, GIF
- **Maximum file size:** 2MB
- **Best results:** Use transparent PNG with logo/clinic name
- **Position:** Top-right corner of prescriptions

## File Structure

```
/doctor_app/
├── uploads/
│   └── logos/
│       └── logo_[client_id]_[timestamp].[ext]
├── dashboard/
│   ├── manage_logo.html (Logo management interface)
│   └── dashboard.php (API handler)
├── add_prescription/
│   └── print.html (Updated with logo display)
└── database/
    └── create_clinic_logos_table.sql
```

## Security Notes

- Logos are stored per client_id (unique per clinic/doctor)
- File type validation prevents non-image uploads
- File size limited to 2MB
- Old logos are automatically deleted when new ones are uploaded
- Files are stored with unique names to prevent conflicts
