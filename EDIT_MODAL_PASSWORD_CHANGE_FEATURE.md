# Edit Modal Password Change Feature

## Overview
Added password change functionality to the Edit modals in both `doctor_list.html` and `receptionalist_list.html`. Users can now optionally change passwords when editing a doctor or receptionist.

## Changes Made

### 1. Frontend Changes (HTML)

#### Files Modified:
- `/workspace/doctor_list/doctor_list.html`
- `/workspace/receptionalist_list/receptionalist_list.html`

#### HTML Structure Added:
```html
<!-- Password Change Section (Optional) -->
<div class="note" style="margin-top:16px;margin-bottom:8px">
  Change Password (Optional - leave blank to keep current password)
</div>

<div class="form-row">
  <input id="editPassword" class="input" placeholder="New Password (min 6 char)" 
         type="password" aria-label="New Password">
</div>

<div class="form-row">
  <input id="editConfirmPassword" class="input" placeholder="Confirm New Password" 
         type="password" aria-label="Confirm New Password">
</div>
```

**Location:** Added between the OTP section and the Update button in the Edit modal.

### 2. Frontend Changes (JavaScript)

#### Element References Added:
```javascript
const editPassword = document.getElementById('editPassword');
const editConfirmPassword = document.getElementById('editConfirmPassword');
```

#### Modal Open Function Updated:
```javascript
function openEditModal(id, name, email, mobile){
  // ... existing code ...
  
  // Clear password fields
  editPassword.value = '';
  editConfirmPassword.value = '';
  
  // ... rest of code ...
}
```

#### Validation Function Updated:

**Doctor List (`checkEditUpdateValidity`):**
```javascript
function checkEditUpdateValidity(){
  const name = (editDName.value||'').trim();
  const mobileVal = (editDMobile.value||'').trim();
  const email = (editDEmail.value||'').trim().toLowerCase();
  const password = editPassword.value || '';
  const confirmPassword = editConfirmPassword.value || '';
  const emailChanged = email !== editOriginalEmail;
  
  const nameOk = name && /^[A-Za-z\s]+$/.test(name);
  const mobileOk = validateMobile(mobileVal);
  const emailOk = validateEmail(email);
  
  // If password is provided, validate it
  let passwordValid = true;
  if(password || confirmPassword){
    passwordValid = password.length >= 6 && password === confirmPassword;
  }
  
  let canUpdate = nameOk && mobileOk && emailOk && passwordValid;
  if(emailChanged && !editOtpVerified){
    canUpdate = false;
  }
  
  updateDoctorBtn.disabled = !canUpdate;
}

// Add password field listeners
editPassword.addEventListener('input', checkEditUpdateValidity);
editConfirmPassword.addEventListener('input', checkEditUpdateValidity);
```

**Receptionist List (`checkEditUpdateValidity`):**
```javascript
function checkEditUpdateValidity(){
  const name = (editRName.value||'').trim();
  const mobileVal = (editRMobile.value||'').trim();
  const email = (editREmail.value||'').trim().toLowerCase();
  const password = editPassword.value || '';
  const confirmPassword = editConfirmPassword.value || '';
  
  const nameValid = /^[A-Za-z\s]+$/.test(name);
  const emailChanged = email !== editOriginalEmail;
  
  // If password is provided, validate it
  let passwordValid = true;
  if(password || confirmPassword){
    passwordValid = password.length >= 6 && password === confirmPassword;
  }
  
  const valid = name && nameValid && validateMobile(mobileVal) && 
                validateEmail(email) && (!emailChanged || editOtpVerified) && passwordValid;
  updateReceptionistBtn.disabled = !valid;
}

// Add password field listeners
editPassword.addEventListener('input', checkEditUpdateValidity);
editConfirmPassword.addEventListener('input', checkEditUpdateValidity);
```

#### Update Function Enhanced:

**Doctor List:**
```javascript
updateDoctorBtn.addEventListener('click', async ()=>{
  // ... existing validation ...
  
  const password = editPassword.value || '';
  const confirmPassword = editConfirmPassword.value || '';
  
  // Validate password if provided
  if(password || confirmPassword){
    if(password.length < 6) return showToast('Password must be at least 6 characters');
    if(password !== confirmPassword) return showToast('Passwords do not match');
  }
  
  updateDoctorBtn.disabled = true;
  editUpdateStatus.textContent = 'Updating...';
  try{
    const params = {
      action: 'update',
      id: editingDoctorId,
      name: name,
      mobile: mobileVal,
      email: email
    };
    
    // Include password only if provided
    if(password){
      params.password = password;
    }
    
    const body = new URLSearchParams(params);
    const res = await fetch(apiList, { method:'POST', body, credentials:'include' });
    // ... rest of code ...
  }
});
```

**Receptionist List:**
```javascript
updateReceptionistBtn.addEventListener('click', async ()=>{
  // ... existing validation ...
  
  const password = editPassword.value || '';
  const confirmPassword = editConfirmPassword.value || '';
  
  // Validate password if provided
  if(password || confirmPassword){
    if(password.length < 6) return showToast('Password must be at least 6 characters');
    if(password !== confirmPassword) return showToast('Passwords do not match');
  }
  
  updateReceptionistBtn.disabled = true;
  editUpdateStatus.textContent = 'Updating...';
  try{
    const payload = {
      id: editingReceptionistId,
      name: name,
      email: email,
      mobile: mobileVal,
      emailChanged: emailChanged,
      otpVerified: editOtpVerified
    };
    
    // Include password only if provided
    if(password){
      payload.password = password;
    }
    
    const body = JSON.stringify(payload);
    const res = await fetch(apiList + '?action=update', { 
      method:'POST', 
      headers:{'Content-Type':'application/json'},
      body: body
    });
    // ... rest of code ...
  }
});
```

### 3. Backend Changes (PHP)

#### Receptionist List (`receptionalist_list.php`):

**Before:**
```php
if($action === 'update'){
    // ... validation code ...
    
    $stmt = $mysqli->prepare("UPDATE users SET name=?, email=?, mobile=? WHERE id=? AND role='receptionalist'");
    $stmt->bind_param('sssi',$name,$email,$mobile,$id);
    $ok = $stmt->execute();
    send_json(['success'=>$ok]);
}
```

**After:**
```php
if($action === 'update'){
    $raw = file_get_contents('php://input'); 
    $d = json_decode($raw,true);
    $id = intval($d['id'] ?? 0);
    $name = trim($d['name'] ?? '');
    $email = strtolower(trim($d['email'] ?? ''));
    $mobile = trim($d['mobile'] ?? '');
    $password = trim($d['password'] ?? '');  // ← New
    $emailChanged = boolval($d['emailChanged'] ?? false);
    $otpVerified = boolval($d['otpVerified'] ?? false);
    
    // ... validation code ...
    
    // Update with or without password
    if($password){
        // Validate password length
        if(strlen($password) < 6){
            send_json(['success'=>false,'message'=>'Password must be at least 6 characters']);
        }
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare("UPDATE users SET name=?, email=?, mobile=?, password=? WHERE id=? AND role='receptionalist'");
        $stmt->bind_param('ssssi',$name,$email,$mobile,$hashedPassword,$id);
    } else {
        $stmt = $mysqli->prepare("UPDATE users SET name=?, email=?, mobile=? WHERE id=? AND role='receptionalist'");
        $stmt->bind_param('sssi',$name,$email,$mobile,$id);
    }
    $ok = $stmt->execute();
    send_json(['success'=>$ok]);
}
```

#### Doctor List (`doctors_list.php`):

**Before:**
```php
if($action === 'update'){
    $raw = file_get_contents('php://input');
    parse_str($raw, $d);
    // ... extract parameters ...
    
    $stmt = $mysqli->prepare("UPDATE users SET name=?, email=?, mobile=? WHERE id=? AND role='user'");
    $stmt->bind_param('sssi',$name,$email,$mobile,$id);
    $ok = $stmt->execute();
    send_json(['success'=>$ok]);
}
```

**After:**
```php
if($action === 'update'){
    $raw = file_get_contents('php://input');
    parse_str($raw, $d);
    $id = intval($d['id'] ?? 0);
    $name = trim($d['name'] ?? '');
    $email = strtolower(trim($d['email'] ?? ''));
    $mobile = trim($d['mobile'] ?? '');
    $password = trim($d['password'] ?? '');  // ← New
    
    // ... validation code ...
    
    // Update with or without password
    if($password){
        // Validate password length
        if(strlen($password) < 6){
            send_json(['success'=>false,'message'=>'Password must be at least 6 characters']);
        }
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare("UPDATE users SET name=?, email=?, mobile=?, password=? WHERE id=? AND role='user'");
        $stmt->bind_param('ssssi',$name,$email,$mobile,$hashedPassword,$id);
    } else {
        $stmt = $mysqli->prepare("UPDATE users SET name=?, email=?, mobile=? WHERE id=? AND role='user'");
        $stmt->bind_param('sssi',$name,$email,$mobile,$id);
    }
    $ok = $stmt->execute();
    send_json(['success'=>$ok]);
}
```

## User Flow

### Updating Without Password Change:
1. User opens Edit modal
2. User modifies name, mobile, or email
3. User **leaves password fields blank**
4. User clicks "Update"
5. **Only name/mobile/email are updated, password remains unchanged**

### Updating With Password Change:
1. User opens Edit modal
2. User modifies any fields (optional)
3. User enters **new password** (min 6 characters)
4. User enters **confirm password** (must match)
5. User clicks "Update"
6. **All fields including password are updated**

## Validation Rules

### Frontend Validation:
- **Password optional**: Can be left blank
- **Minimum length**: 6 characters (if provided)
- **Password match**: Password and Confirm Password must match
- **Real-time validation**: Update button disabled if validation fails
- **Toast notifications**: Clear error messages

### Backend Validation:
- **Password optional**: Server accepts updates without password
- **Minimum length check**: Validates 6 characters minimum
- **Password hashing**: Uses `password_hash()` with `PASSWORD_DEFAULT`
- **Database update**: Conditional SQL based on password presence

## Security Features

1. **Password Hashing**: All passwords hashed using PHP's `password_hash()`
2. **Optional Update**: Password only updated if explicitly provided
3. **Strong Validation**: Both client and server-side validation
4. **Minimum Length**: Enforced 6-character minimum
5. **Match Verification**: Confirm password must match
6. **Secure Storage**: Never stores plain text passwords

## UI/UX Features

1. **Clear Instructions**: "Optional - leave blank to keep current password"
2. **Password Type**: Input fields use `type="password"` for privacy
3. **Visual Feedback**: Button disabled until validation passes
4. **Error Messages**: Specific toast notifications for errors
5. **Field Clearing**: Password fields reset when modal opens
6. **Real-time Validation**: Immediate feedback as user types

## Files Modified

### Frontend:
1. `/workspace/doctor_list/doctor_list.html`
   - Added password input fields to Edit modal
   - Added JavaScript validation logic
   - Updated update handler to include password

2. `/workspace/receptionalist_list/receptionalist_list.html`
   - Added password input fields to Edit modal
   - Added JavaScript validation logic
   - Updated update handler to include password

### Backend:
3. `/workspace/doctor_list/doctors_list.php`
   - Added password parameter handling
   - Added conditional password update logic
   - Added password hashing

4. `/workspace/receptionalist_list/receptionalist_list.php`
   - Added password parameter handling
   - Added conditional password update logic
   - Added password hashing

## Testing Checklist

- [x] Edit modal shows password fields
- [x] Password fields are optional (can be left blank)
- [x] Update without password works correctly
- [x] Update with valid password works correctly
- [x] Password minimum length validation (6 chars)
- [x] Password match validation works
- [x] Password mismatch shows error toast
- [x] Short password shows error toast
- [x] Password is hashed in database
- [x] Update button enables/disables correctly
- [x] Both doctor and receptionist lists work
- [x] No linter errors

## Verification

All changes tested and verified:
- ✅ No linter errors in all files
- ✅ Password fields appear in Edit modals
- ✅ Optional password update works correctly
- ✅ Validation logic is comprehensive
- ✅ Backend properly handles password updates
- ✅ Passwords are securely hashed
- ✅ Consistent behavior across both lists

## Benefits

1. **Enhanced Security**: Admins can update user passwords as needed
2. **User Convenience**: Optional password change in same form
3. **Password Recovery**: Admins can reset passwords for users
4. **Consistent Interface**: Same pattern in both doctor and receptionist lists
5. **Secure Implementation**: Proper hashing and validation
6. **Clear UX**: Users understand it's optional
