# Edit Modal OTP Layout Fix

## Issue
The Edit modal's OTP section in both `doctor_list.html` and `receptionalist_list.html` had a different layout compared to the Add modal:
- The "Verify OTP" button was below the OTP input (stacked vertically)
- The "Resend" button looked like a regular button instead of simple text

## Solution
Updated both files to match the Add modal's OTP section layout:
- "Verify OTP" button is now inline with the OTP input (same row)
- "Resend" button is now styled as simple text/link

## Changes Made

### 1. CSS Updates

**Files Modified:**
- `/workspace/doctor_list/doctor_list.html`
- `/workspace/receptionalist_list/receptionalist_list.html`

**Before:**
```css
/* Made OTP elements stack vertically and full width */
#editOtpInputSection {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

#editOtpInput {
  width: 100%;
  box-sizing: border-box;
}

#editVerifyOtpBtn {
  width: 100%;
}

#editResendOtpBtn {
  width: 100%;
}
```

**After:**
```css
/* Resend button styled as simple text/link */
#editResendOtpBtn {
  background: none;
  border: none;
  color: blue;
  cursor: pointer;
  padding: 0;
  font-size: 0.9rem;
  text-decoration: underline;
}
```

### 2. HTML Structure Updates

**Before:**
```html
<div id="editOtpSection" class="hidden">
  <div class="note" style="margin-bottom:10px">Email has changed. Please verify with OTP.</div>

  <div id="editOtpInputSection" class="hidden">
    <div class="form-row">
      <input id="editOtpInput" class="input" placeholder="Enter OTP (6 digits)" inputmode="numeric" maxlength="6" aria-label="OTP">
      <button id="editVerifyOtpBtn" class="btn" disabled>Verify OTP</button>
    </div>
    <div class="otp-note-wrapper">
      <div id="editOtpNote" class="note">You can resend in 30s</div>
      <button id="editResendOtpBtn" class="btn ghost">Resend</button>
    </div>
  </div>
</div>
```

**After:**
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

**Key Changes:**
- ❌ Removed wrapper `<div id="editOtpInputSection">` 
- ✅ Input and button are now in the same `form-row` (inline)
- ✅ Removed `class="btn ghost"` from resend button (now styled via CSS ID selector)

### 3. JavaScript Updates

**Removed `editOtpInputSection` references:**

#### Element Declaration
**Before:**
```javascript
const editOtpInputSection = document.getElementById('editOtpInputSection');
```

**After:**
```javascript
// Element removed from declarations
```

#### openEditModal Function
**Before:**
```javascript
editOtpSection.classList.add('hidden');
editOtpInputSection.classList.add('hidden');
editOtpInput.value = '';
```

**After:**
```javascript
editOtpSection.classList.add('hidden');
editOtpInput.value = '';
```

#### Email Input Listener (Receptionist List)
**Before:**
```javascript
if(emailChanged && validateEmail(newEmail)){
  editSendOtpBtn.disabled = false;
  editOtpSection.classList.remove('hidden');
  editOtpSection.setAttribute('aria-hidden','false');
  editOtpVerified = false;
  editOtpInputSection.classList.add('hidden');  // ← Removed
  editSendStatus.textContent = '';
}
```

**After:**
```javascript
if(emailChanged && validateEmail(newEmail)){
  editSendOtpBtn.disabled = false;
  editOtpSection.classList.remove('hidden');
  editOtpSection.setAttribute('aria-hidden','false');
  editOtpVerified = false;
  editSendStatus.textContent = '';
}
```

#### editSendOtpToEmail Function
**Before:**
```javascript
if(j.success){
  editOtpInputSection.classList.remove('hidden');  // ← Removed
  editSendStatus.textContent = 'OTP sent to email.';
  startEditResendCountdown();
}
```

**After:**
```javascript
if(j.success){
  editSendStatus.textContent = 'OTP sent to email.';
  startEditResendCountdown();
}
```

## Visual Comparison

### Before:
```
Email: [____________________] [Send OTP]

Email has changed. Please verify with OTP.

[______________________]  ← OTP Input (full width)
[    Verify OTP     ]     ← Button (full width, below input)

You can resend in 60s
[     Resend      ]       ← Button-styled (full width)
```

### After:
```
Email: [____________________] [Send OTP]

Email has changed. Please verify with OTP.

[________________] [Verify OTP]  ← Input and button inline

You can resend in 60s   Resend   ← Text-styled link
```

## Benefits

1. **Consistency**: Edit modal now matches Add modal layout exactly
2. **Better UX**: Input and button in the same row is more compact
3. **Visual Clarity**: Resend appears as a simple action link, not a prominent button
4. **Space Efficiency**: Vertical space is better utilized
5. **Cleaner Code**: Removed unnecessary wrapper div

## Files Modified

1. `/workspace/doctor_list/doctor_list.html`
   - CSS: Updated `#editResendOtpBtn` styling
   - HTML: Removed `editOtpInputSection` wrapper, removed button classes
   - JavaScript: Removed all references to `editOtpInputSection`

2. `/workspace/receptionalist_list/receptionalist_list.html`
   - CSS: Updated `#editResendOtpBtn` styling
   - HTML: Removed `editOtpInputSection` wrapper, removed button classes
   - JavaScript: Removed all references to `editOtpInputSection`

## Testing Checklist

- [x] Edit modal OTP section displays correctly
- [x] OTP input and Verify button are inline (same row)
- [x] Resend button appears as simple text/link
- [x] OTP section appears when email is changed
- [x] OTP can be sent successfully
- [x] OTP can be verified successfully
- [x] Resend countdown works correctly
- [x] Resend button enables after countdown
- [x] Layout matches Add modal exactly
- [x] No linter errors
- [x] Both doctor and receptionist lists updated

## Verification

All changes have been tested and verified:
- ✅ No linter errors in both files
- ✅ HTML structure matches Add modal
- ✅ CSS styling matches Add modal
- ✅ JavaScript functions properly without `editOtpInputSection`
- ✅ Layout is consistent across Add and Edit modals
