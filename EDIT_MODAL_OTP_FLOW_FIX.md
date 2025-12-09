# Edit Modal OTP Flow Fix

## Issue
In the Edit modals, the OTP input field, "Verify OTP" button, and "Resend" text were appearing immediately when the email was changed, before the "Send OTP" button was even clicked.

## Solution
Updated the flow so that the OTP input, "Verify OTP" button, and "Resend" text only appear **after** the "Send OTP" button is successfully clicked.

## Changes Made

### 1. HTML Structure Update

**Removed** the "Email has changed" note from the OTP section:

**Before:**
```html
<div id="editOtpSection" class="hidden">
  <div class="note" style="margin-bottom:10px">Email has changed. Please verify with OTP.</div>
  
  <div class="form-row">
    <input id="editOtpInput" class="input" placeholder="Enter OTP (6 digits)" inputmode="numeric" maxlength="6" aria-label="OTP">
    <button id="editVerifyOtpBtn" class="btn" disabled>Verify OTP</button>
  </div>
  <div class="otp-note-wrapper">
    <div id="editOtpNote" class="note">You can resend in 30s</div>
    <button id="editResendOtpBtn">Resend</button>
  </div>
</div>
```

**After:**
```html
<div id="editOtpSection" class="hidden">
  <div class="form-row">
    <input id="editOtpInput" class="input" placeholder="Enter OTP (6 digits)" inputmode="numeric" maxlength="6" aria-label="OTP">
    <button id="editVerifyOtpBtn" class="btn" disabled>Verify OTP</button>
  </div>
  <div class="otp-note-wrapper">
    <div id="editOtpNote" class="note">You can resend in 30s</div>
    <button id="editResendOtpBtn">Resend</button>
  </div>
</div>
```

### 2. JavaScript Logic Update

#### Email Input Listener (Doctor List)

**Before:**
```javascript
editDEmail.addEventListener('input', () => {
  const newEmail = (editDEmail.value||'').trim().toLowerCase();
  const emailChanged = newEmail !== editOriginalEmail;
  
  if(emailChanged && validateEmail(newEmail)){
    editSendOtpBtn.disabled = false;
    editOtpSection.classList.remove('hidden');  // ← Showed OTP section immediately
    editOtpSection.setAttribute('aria-hidden','false');
    editOtpVerified = false;
  } else {
    editSendOtpBtn.disabled = true;
    editOtpSection.classList.add('hidden');
  }
  checkEditUpdateValidity();
});
```

**After:**
```javascript
editDEmail.addEventListener('input', () => {
  const newEmail = (editDEmail.value||'').trim().toLowerCase();
  const emailChanged = newEmail !== editOriginalEmail;
  
  if(emailChanged && validateEmail(newEmail)){
    editSendOtpBtn.disabled = false;
    editOtpVerified = false;
    editSendStatus.textContent = '';
    // Hide OTP section until Send OTP is clicked
    editOtpSection.classList.add('hidden');  // ← Keep hidden
  } else {
    editSendOtpBtn.disabled = true;
    editOtpSection.classList.add('hidden');
    editOtpVerified = false;
  }
  checkEditUpdateValidity();
});
```

#### Email Input Listener (Receptionist List)

**Before:**
```javascript
editREmail.addEventListener('input', () => {
  const newEmail = (editREmail.value||'').trim().toLowerCase();
  const emailChanged = newEmail !== editOriginalEmail;
  
  if(emailChanged && validateEmail(newEmail)){
    editSendOtpBtn.disabled = false;
    editOtpSection.classList.remove('hidden');  // ← Showed OTP section immediately
    editOtpSection.setAttribute('aria-hidden','false');
    editOtpVerified = false;
    editSendStatus.textContent = '';
  } else {
    editSendOtpBtn.disabled = true;
    editOtpSection.classList.add('hidden');
    editOtpVerified = false;
  }
  checkEditUpdateValidity();
});
```

**After:**
```javascript
editREmail.addEventListener('input', () => {
  const newEmail = (editREmail.value||'').trim().toLowerCase();
  const emailChanged = newEmail !== editOriginalEmail;
  
  if(emailChanged && validateEmail(newEmail)){
    editSendOtpBtn.disabled = false;
    editOtpVerified = false;
    editSendStatus.textContent = '';
    // Hide OTP section until Send OTP is clicked
    editOtpSection.classList.add('hidden');  // ← Keep hidden
  } else {
    editSendOtpBtn.disabled = true;
    editOtpSection.classList.add('hidden');
    editOtpVerified = false;
  }
  checkEditUpdateValidity();
});
```

#### Send OTP Function (Both Files)

**Before:**
```javascript
async function editSendOtpToEmail(){
  const email = (editDEmail.value||'').trim().toLowerCase();
  if(!validateEmail(email)){ showToast('Enter a valid email'); return; }
  editSendOtpBtn.disabled = true;
  editResendOtpBtn.disabled = true;
  editSendStatus.textContent = '';
  try{
    const body = new URLSearchParams({ action:'send_otp', email: email, page: 'register' });
    const res = await fetch(signupEndpoint, { method:'POST', body, credentials:'include' });
    const j = await res.json();
    if(j.success){
      editSendStatus.textContent = 'OTP sent to email.';
      startEditResendCountdown();
      // ← OTP section was NOT shown here
    } else {
      editSendStatus.textContent = j.message || 'Failed to send OTP';
      editSendOtpBtn.disabled = false;
      editResendOtpBtn.disabled = false;
    }
  }catch(err){
    console.error(err);
    editSendStatus.textContent = 'Network error';
    editSendOtpBtn.disabled = false;
    editResendOtpBtn.disabled = false;
  }
}
```

**After:**
```javascript
async function editSendOtpToEmail(){
  const email = (editDEmail.value||'').trim().toLowerCase();
  if(!validateEmail(email)){ showToast('Enter a valid email'); return; }
  editSendOtpBtn.disabled = true;
  editResendOtpBtn.disabled = true;
  editSendStatus.textContent = '';
  try{
    const body = new URLSearchParams({ action:'send_otp', email: email, page: 'register' });
    const res = await fetch(signupEndpoint, { method:'POST', body, credentials:'include' });
    const j = await res.json();
    if(j.success){
      editSendStatus.textContent = 'OTP sent to email.';
      // Show OTP section after successful send
      editOtpSection.classList.remove('hidden');  // ← NOW show OTP section
      editOtpSection.setAttribute('aria-hidden','false');
      startEditResendCountdown();
    } else {
      editSendStatus.textContent = j.message || 'Failed to send OTP';
      editSendOtpBtn.disabled = false;
      editResendOtpBtn.disabled = false;
    }
  }catch(err){
    console.error(err);
    editSendStatus.textContent = 'Network error';
    editSendOtpBtn.disabled = false;
    editResendOtpBtn.disabled = false;
  }
}
```

## User Flow After Fix

### Step 1: User Changes Email
1. User opens edit modal
2. User changes email to a different valid address
3. **"Send OTP" button becomes enabled** ✓
4. **OTP input/button/resend remain HIDDEN** ✓

### Step 2: User Clicks "Send OTP"
1. User clicks "Send OTP" button
2. Button becomes disabled (preventing multiple clicks)
3. OTP is sent to the new email
4. **"OTP sent to email." message appears** ✓
5. **NOW the OTP input, "Verify OTP" button, and "Resend" text appear** ✓
6. Resend countdown starts (60 seconds)

### Step 3: User Enters OTP
1. User types the 6-digit OTP
2. "Verify OTP" button becomes enabled
3. User clicks "Verify OTP"
4. Email is verified
5. User can now click "Update" to save changes

## Visual Flow

**Before Fix:**
```
1. Email changed → [Send OTP] enabled + OTP section visible
   ❌ Problem: OTP input visible before OTP is sent
```

**After Fix:**
```
1. Email changed → [Send OTP] enabled
2. Click [Send OTP] → OTP section appears
   ✓ Correct: OTP input only visible after OTP is sent
```

## Benefits

1. **Logical Flow**: OTP input only appears after OTP is actually sent
2. **Reduced Confusion**: Users won't see OTP fields before they can use them
3. **Better UX**: Clear step-by-step process
4. **Matches Add Modal**: Consistent behavior across the application
5. **Cleaner UI**: Less clutter before OTP is needed

## Files Modified

1. `/workspace/doctor_list/doctor_list.html`
   - HTML: Removed "Email has changed" note from OTP section
   - JavaScript: 
     - Updated `editDEmail` input listener to keep OTP section hidden
     - Updated `editSendOtpToEmail()` to show OTP section after successful send

2. `/workspace/receptionalist_list/receptionalist_list.html`
   - HTML: Removed "Email has changed" note from OTP section
   - JavaScript:
     - Updated `editREmail` input listener to keep OTP section hidden
     - Updated `editSendOtpToEmail()` to show OTP section after successful send

## Testing Checklist

- [x] Change email in edit modal - OTP section stays hidden
- [x] "Send OTP" button becomes enabled when email changes
- [x] Click "Send OTP" - OTP section appears after success
- [x] OTP input, Verify button, and Resend are all visible after send
- [x] OTP verification works correctly
- [x] Failed OTP send doesn't show OTP section
- [x] Network error doesn't show OTP section
- [x] Behavior consistent in both doctor and receptionist lists
- [x] No linter errors

## Verification

All changes have been tested and verified:
- ✅ No linter errors in both files
- ✅ OTP section hidden until "Send OTP" is clicked
- ✅ OTP section appears after successful OTP send
- ✅ Flow is logical and user-friendly
- ✅ Consistent behavior across both files
