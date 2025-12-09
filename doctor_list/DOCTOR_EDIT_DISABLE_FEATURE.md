# Doctor Edit and Disable/Enable Feature

## Summary
Added Edit and Disable/Enable functionality to the doctor list table, matching the features available in the receptionist list.

## Changes Made

### 1. Frontend Changes (`doctor_list.html`)

#### CSS Additions:
- **Disable/Enable Button Styles**: Added styling for `.action-btn.disable` and `.action-btn.enable` buttons
  - Disable button: Orange background (#ff9800)
  - Enable button: Green background (#4caf50)
- **Edit OTP Section Styles**: Added CSS for `#editOtpInputSection`, `#editOtpInput`, `#editVerifyOtpBtn`, and `#editResendOtpBtn` to ensure proper layout

#### HTML Changes:
- **Table Structure**: Added "Status" column header to the table
  - Updated table from 6 columns to 7 columns
  - Added Status column between Role and Created columns

- **Edit Modal**: Added complete edit modal structure after the Add Doctor modal
  - Modal overlay with backdrop
  - Edit form fields: Name, Mobile, Email
  - OTP verification section for email changes
  - Update Doctor button
  - Close button and ESC key support

#### JavaScript Changes:
- **renderTable() Function**: Enhanced to include:
  - Status badge display (Active/Inactive)
  - Disable/Enable button based on current status
  - Edit button with doctor data attributes
  - Delete button (existing)
  
- **Toggle Status Functionality**:
  - Event listener for `.toggle-status` buttons
  - Confirmation dialog before status change
  - API call to `toggle_status` action
  - Automatic table refresh after success

- **Edit Modal Logic**: Added complete edit functionality:
  - `openEditModal()`: Opens modal with doctor's current data
  - `closeEditModal()`: Closes modal and resets state
  - Email change detection with OTP verification requirement
  - OTP send, verify, and resend functionality
  - Form validation for name, mobile, and email
  - `checkEditUpdateValidity()`: Enables/disables update button based on form state
  - Update API call with success/error handling

### 2. Backend Changes (`doctors_list.php`)

**Note**: File was renamed from `doctor_list.php` to `doctors_list.php` to match the API endpoint reference in the HTML.

#### Added Actions:
1. **`list` (Enhanced)**:
   - Now includes `status` column in SELECT query
   - Added automatic column creation if `status` doesn't exist
   - Returns `COALESCE(status, 'active')` as status

2. **`update` (New)**:
   - Accepts: `id`, `name`, `email`, `mobile`
   - Validates all required fields
   - Checks for duplicate email (excluding current user)
   - Updates doctor information
   - Returns success/failure status

3. **`toggle_status` (New)**:
   - Accepts: `id`, `status` (active/inactive)
   - Validates status values
   - Ensures `status` and `session_version` columns exist
   - Updates user status
   - Increments `session_version` to force logout
   - Cleans up session files when disabling a user
   - Returns success/failure status

## Database Schema Updates

The following columns are automatically added if they don't exist:

```sql
-- Added by the list action
ALTER TABLE users ADD COLUMN status VARCHAR(20) DEFAULT 'active';

-- Added by the toggle_status action
ALTER TABLE users ADD COLUMN session_version INT DEFAULT 1;
```

## Features

### Edit Doctor
1. Click the "Edit" button on any doctor row
2. Edit modal opens with current doctor information
3. Modify name and/or mobile (no OTP required)
4. If email is changed:
   - "Send OTP" button becomes enabled
   - OTP section appears
   - Must verify new email with OTP before saving
5. Click "Update Doctor" to save changes
6. Modal closes and table refreshes automatically

### Disable/Enable Doctor
1. Click "Disable" button to deactivate a doctor account
2. Status changes to "Inactive" (red badge)
3. Doctor is immediately logged out (session cleared)
4. Button changes to "Enable"
5. Click "Enable" to reactivate the account
6. Status changes back to "Active" (green badge)

## UI/UX Improvements
- **Consistent Design**: Matches the receptionist list styling exactly
- **Visual Feedback**: Toast notifications for all actions
- **Confirmation Dialogs**: Prevents accidental status changes
- **Real-time Validation**: Form fields validate as user types
- **Responsive Design**: Works on mobile and desktop
- **Accessibility**: ARIA labels and keyboard navigation support

## Security Features
- **Session Invalidation**: Disabling a user immediately logs them out
- **Email Verification**: Email changes require OTP confirmation
- **Duplicate Email Check**: Prevents multiple accounts with same email
- **Role-based Filtering**: All operations only affect users with role='user' (doctors)
- **Input Validation**: Server-side validation for all fields
- **Prepared Statements**: SQL injection protection

## Testing Checklist
- [x] Edit doctor with name/mobile change (no email change)
- [x] Edit doctor with email change (requires OTP)
- [x] Toggle status from Active to Inactive
- [x] Toggle status from Inactive to Active
- [x] Delete doctor
- [x] Table updates after each action
- [x] Toast notifications display correctly
- [x] Modal closes on ESC key
- [x] Modal closes on overlay click
- [x] Form validation works correctly
- [x] Duplicate email detection
- [x] Status badge displays correctly
- [x] Responsive design on mobile

## Files Modified
1. `/workspace/doctor_list/doctor_list.html` - Frontend UI and logic
2. `/workspace/doctor_list/doctors_list.php` - Backend API (renamed from doctor_list.php)

## Dependencies
- Existing signup.php endpoint for OTP functionality
- MySQL database with `users` table
- Session management system

## Future Enhancements (Optional)
- Bulk enable/disable functionality
- Export doctor list to CSV
- Advanced filtering options
- Doctor activity logs
- Password reset for doctors
- Profile picture upload
