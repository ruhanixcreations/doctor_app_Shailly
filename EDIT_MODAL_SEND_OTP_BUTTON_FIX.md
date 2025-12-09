# Edit Modal "Send OTP" Button Fix

## Issue
The "Send OTP" button in the edit modals for both doctor list and receptionist list was not properly disabled when the email remained unchanged from the original database value.

## Solution
Updated both `doctor_list.html` and `receptionalist_list.html` to properly manage the "Send OTP" button state.

## Changes Made

### 1. Initial State on Modal Open
When the edit modal opens, the "Send OTP" button is now explicitly disabled since the email hasn't been changed yet.

**Files Modified:**
- `/workspace/doctor_list/doctor_list.html` - `openEditModal()` function
- `/workspace/receptionalist_list/receptionalist_list.html` - `openEditModal()` function

**Code Added:**
```javascript
// Disable Send OTP button initially since email hasn't changed
editSendOtpBtn.disabled = true;
```

### 2. Dynamic Button State Management
The email input event listener now properly enables/disables the "Send OTP" button based on whether the email has changed.

**Logic:**
- **Button DISABLED when:**
  - Email is the same as the original email (no change)
  - Email is invalid (doesn't match email format)
  
- **Button ENABLED when:**
  - Email is different from the original email AND
  - Email is valid (matches email format)

**Implementation:**

#### Doctor List (`doctor_list.html`):
```javascript
editDEmail.addEventListener('input', () => {
  const newEmail = (editDEmail.value||'').trim().toLowerCase();
  const emailChanged = newEmail !== editOriginalEmail;
  
  if(emailChanged && validateEmail(newEmail)){
    editSendOtpBtn.disabled = false;  // Enable when changed AND valid
    editOtpSection.classList.remove('hidden');
    editOtpSection.setAttribute('aria-hidden','false');
    editOtpVerified = false;
  } else {
    editSendOtpBtn.disabled = true;   // Disable when same OR invalid
    editOtpSection.classList.add('hidden');
  }
  checkEditUpdateValidity();
});
```

#### Receptionist List (`receptionalist_list.html`):
```javascript
editREmail.addEventListener('input', () => {
  const newEmail = (editREmail.value||'').trim().toLowerCase();
  const emailChanged = newEmail !== editOriginalEmail;
  
  if(emailChanged && validateEmail(newEmail)){
    editSendOtpBtn.disabled = false;  // Enable when changed AND valid
    editOtpSection.classList.remove('hidden');
    editOtpSection.setAttribute('aria-hidden','false');
    editOtpVerified = false;
    editOtpInputSection.classList.add('hidden');
    editSendStatus.textContent = '';
  } else {
    editSendOtpBtn.disabled = true;   // Disable when same OR invalid
    editOtpSection.classList.add('hidden');
    editOtpVerified = false;
  }
  checkEditUpdateValidity();
});
```

## Behavior After Fix

### Scenario 1: Opening Edit Modal
1. User clicks "Edit" on a doctor/receptionist
2. Modal opens with current data
3. **"Send OTP" button is DISABLED** ✓
4. OTP section is hidden

### Scenario 2: User Doesn't Change Email
1. User edits name or mobile
2. Email field remains unchanged
3. **"Send OTP" button stays DISABLED** ✓
4. User can update without OTP verification

### Scenario 3: User Changes Email to Invalid Format
1. User types invalid email (e.g., "test@")
2. **"Send OTP" button stays DISABLED** ✓
3. OTP section remains hidden

### Scenario 4: User Changes Email to Valid Format
1. User types valid email different from original (e.g., "newemail@test.com")
2. **"Send OTP" button becomes ENABLED** ✓
3. OTP section appears
4. User must verify new email before updating

### Scenario 5: User Changes Email Back to Original
1. User types a different email (button enables)
2. User changes it back to original email
3. **"Send OTP" button becomes DISABLED again** ✓
4. OTP section hides

## Files Modified
1. `/workspace/doctor_list/doctor_list.html`
   - `openEditModal()` function
   - `editDEmail` input event listener

2. `/workspace/receptionalist_list/receptionalist_list.html`
   - `openEditModal()` function
   - `editREmail` input event listener

## Benefits
- **Better UX**: Users don't see an enabled "Send OTP" button when no email change is needed
- **Clear Intent**: Button state clearly indicates when OTP verification is required
- **Prevents Confusion**: Users won't accidentally send OTP for unchanged emails
- **Consistent Behavior**: Both doctor and receptionist lists behave identically
- **Validation**: Button only enables for valid email changes

## Testing Checklist
- [x] Open edit modal - button is disabled
- [x] Type same email as original - button stays disabled
- [x] Type different valid email - button enables
- [x] Type different invalid email - button stays disabled
- [x] Change email back to original - button disables again
- [x] Update without changing email - no OTP required
- [x] Update with changed email - OTP verification required
- [x] No linter errors

## Verification
All changes have been tested and verified:
- No linter errors
- Button state logic is consistent across both files
- Initial state is properly set on modal open
- Dynamic state management works correctly
