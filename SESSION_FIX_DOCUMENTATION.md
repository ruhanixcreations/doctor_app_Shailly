# Session Configuration Fix - Automatic Logout Issue

## 🔴 Root Cause Identified

The automatic logout after a few seconds was caused by **session cookie domain mismatch** between PHP files:

### Before Fix:
```
signin.php:        domain => '.ruhanixlegal.in'  ✅
check_session.php: domain => ''                  ❌ MISMATCH!
logout.php:        domain => ''                  ❌ MISMATCH!
```

### What Was Happening:

1. ✅ User signs in via `signin.php`
2. ✅ Session cookie created with domain `.ruhanixlegal.in`
3. ✅ User lands on dashboard with `isLoggedIn=true`
4. ⏱️ After 3 seconds, `header.js` calls `check_session.php` to verify session
5. ❌ `check_session.php` starts NEW session with domain `''` (empty)
6. ❌ Cannot read the session from `signin.php` (different domain)
7. ❌ Returns `{success: false}` 
8. ❌ `header.js` calls `forceLogout()`
9. 🚨 User automatically logged out!

---

## ✅ Fixes Applied

### Fix 1: Aligned Session Cookie Domain in `check_session.php`
**File:** `check_session.php` line 9

**Before:**
```php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',  // ❌ Empty domain
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);
```

**After:**
```php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '.ruhanixlegal.in',  // ✅ Matches signin.php
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);
```

### Fix 2: Aligned Session Cookie Domain in `logout.php`
**File:** `logout.php` line 21

**Before:**
```php
'domain' => '',  // ❌ Empty domain
```

**After:**
```php
'domain' => '.ruhanixlegal.in',  // ✅ Matches signin.php
```

### Fix 3: Reduced Session Check Frequency
**File:** `header.js` line 457

**Before:**
```javascript
}, 3000);  // Check every 3 seconds - TOO AGGRESSIVE
```

**After:**
```javascript
}, 30000);  // Check every 30 seconds - More reasonable
```

**Rationale:** Checking every 3 seconds was too aggressive and could cause:
- Race conditions during session creation
- Unnecessary server load
- Network timeouts triggering false logouts
- Battery drain on mobile devices

---

## 🔒 Session Configuration Standard

**ALL PHP files that use sessions MUST have identical cookie parameters:**

```php
session_set_cookie_params([
    'lifetime' => 0,                  // Session cookie (expires when browser closes)
    'path' => '/',                    // Available across entire site
    'domain' => '.ruhanixlegal.in',   // ✅ MUST MATCH ACROSS ALL FILES
    'secure' => false,                // Set to true when using HTTPS
    'httponly' => true,               // Prevent JavaScript access (security)
    'samesite' => 'Lax'              // CSRF protection
]);
```

### Files Currently Using Correct Configuration:
- ✅ `signin.php`
- ✅ `signup.php`
- ✅ `check_session.php` (fixed)
- ✅ `logout.php` (fixed)
- ✅ `profile.php`
- ✅ `dashboard.php`

---

## 🧪 Testing Instructions

### Test 1: Basic Signin Flow
1. Clear browser cache and localStorage (F12 → Application → Clear storage)
2. Navigate to signin page
3. Sign in with valid credentials
4. **Expected:** Land on dashboard
5. **Wait 35+ seconds** (longer than check interval)
6. **Expected:** Still logged in, no automatic logout
7. ✅ Navigate between pages - should stay logged in

### Test 2: Session Persistence
1. Sign in successfully
2. Open browser DevTools (F12)
3. Go to Console tab
4. **Should NOT see:** `❌ Session invalid` messages
5. **After 30 seconds:** Session check should complete silently
6. ✅ No logout should occur

### Test 3: Check localStorage
1. Sign in successfully
2. Open DevTools → Application → Local Storage
3. **Verify these keys exist:**
   - `isLoggedIn: "true"`
   - `user_id: [number]`
   - `userName: [string]`
   - `role: [string]`
   - `client_id: [string]`

### Test 4: Check Session Cookie
1. Sign in successfully
2. Open DevTools → Application → Cookies
3. **Find session cookie (usually PHPSESSID)**
4. **Verify:**
   - Domain: `.ruhanixlegal.in`
   - Path: `/`
   - HttpOnly: ✓
   - SameSite: Lax

### Test 5: Manual Logout
1. Sign in successfully
2. Click profile icon → Logout
3. **Expected:** Confirmation prompt
4. Click OK
5. **Expected:** Redirect to signin page
6. **Verify:** All localStorage cleared
7. ✅ Cannot access protected pages

---

## 🐛 Debugging Guide

### If Automatic Logout Still Occurs:

#### Step 1: Check Browser Console
```
F12 → Console tab
Look for:
- "❌ Session invalid: [message]"
- "⚠️ Session check error: [error]"
```

#### Step 2: Check Network Tab
```
F12 → Network tab → Filter: check_session.php
- Should see requests every 30 seconds
- Click on request → Preview tab
- Should show: {"success": true, "message": "User logged in", ...}
```

#### Step 3: Check PHP Session Files
```bash
# SSH into server
ls -la /path/to/workspace/sessions/
# Should see sess_[session_id] files
# Check file contents:
cat sessions/sess_[tab-complete]
```

#### Step 4: Check PHP Error Logs
```bash
# Check for PHP errors
tail -f /var/log/php-fpm/error.log
# or
tail -f /var/log/apache2/error.log
```

#### Step 5: Test Session Isolation
1. Open signin page
2. Open DevTools → Console
3. Run:
```javascript
localStorage.setItem('isLoggedIn', 'true');
localStorage.setItem('userName', 'TestUser');
```
4. Refresh page
5. Header should show profile icon
6. Check if automatic logout occurs

---

## 🔍 Session Flow Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    User Signs In                             │
└─────────────────────┬───────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────┐
│  signin.php                                                  │
│  • Creates PHP session with domain .ruhanixlegal.in        │
│  • Sets $_SESSION variables                                 │
│  • Returns JSON with user data                              │
└─────────────────────┬───────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────┐
│  signin.html (JavaScript)                                    │
│  • Receives response                                         │
│  • Sets localStorage.isLoggedIn = 'true'                   │
│  • Sets other user data in localStorage                     │
│  • Redirects to dashboard                                   │
└─────────────────────┬───────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────┐
│  dashboard.html loads                                        │
│  • header.js loads                                          │
│  • Checks localStorage.isLoggedIn                           │
│  • Shows user profile icon                                  │
└─────────────────────┬───────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────┐
│  header.js starts session monitoring                         │
│  • Every 30 seconds, calls check_session.php               │
└─────────────────────┬───────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────┐
│  check_session.php                                           │
│  • Reads PHP session with domain .ruhanixlegal.in          │
│  • ✅ Same domain = Can read session!                       │
│  • Validates session data                                   │
│  • Returns {success: true} if valid                         │
└─────────────────────┬───────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────┐
│  header.js receives response                                 │
│  • success: true → Continue monitoring silently             │
│  • success: false → Call forceLogout()                     │
└─────────────────────────────────────────────────────────────┘
```

---

## 📊 Before vs After Comparison

### Before Fix:
```
Time  Action                           Result
0s    User signs in                    ✅ Success
0s    Land on dashboard                ✅ Works
3s    Session check (domain mismatch)  ❌ Fails
3s    forceLogout() called             ❌ Logged out
3s    Redirect to signin               😞 User frustrated
```

### After Fix:
```
Time  Action                           Result
0s    User signs in                    ✅ Success
0s    Land on dashboard                ✅ Works
30s   Session check (domain matches)   ✅ Success
60s   Session check                    ✅ Success
90s   Session check                    ✅ Success
...   User continues working           😊 Happy user
```

---

## ⚠️ Important Notes

### Cookie Domain Format:
- `.ruhanixlegal.in` (with leading dot) = Works on all subdomains
- `ruhanixlegal.in` (no leading dot) = Works only on exact domain
- `''` (empty) = Works only on current domain/subdomain

### Session Security:
- `httponly: true` prevents XSS attacks from accessing session cookie
- `samesite: 'Lax'` prevents CSRF attacks
- `secure: false` should be `true` in production with HTTPS

### Session Lifetime:
- `lifetime: 0` = Session cookie (deleted when browser closes)
- For "Remember Me" functionality, use a longer lifetime

---

## 📝 Files Modified (Summary)

1. **check_session.php** - Fixed domain from `''` to `.ruhanixlegal.in`
2. **logout.php** - Fixed domain from `''` to `.ruhanixlegal.in`
3. **header.js** - Reduced check interval from 3s to 30s

---

## ✅ Expected Behavior After All Fixes

1. ✅ Sign in with password → Stay logged in
2. ✅ Sign in with OTP → Stay logged in
3. ✅ Navigate between pages → Stay logged in
4. ✅ Close tab and reopen → Still logged in (if browser not closed)
5. ✅ Wait idle → Session checks pass silently
6. ✅ Manual logout → Properly logs out and clears data
7. ✅ Session expiry → Graceful logout with message

---

**Last Updated:** 2025-11-26
**Status:** ✅ Session Configuration Fixed
**Tested:** Ready for production testing
