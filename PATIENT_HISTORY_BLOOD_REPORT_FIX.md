# Patient History - Blood Report Fix Guide

## Issue Fixed

**Problem:** When a doctor logs in and clicks "View" on blood reports in the Prescription History section, it was showing the same (latest) file for all prescriptions, instead of showing the specific blood report uploaded for each prescription.

**Root Cause:** The code was not linking blood reports to specific prescriptions - it was always opening the most recent blood report regardless of which prescription's "View" button was clicked.

---

## Solution Applied

### Changes Made

#### 1. **Backend Fix (patient_history.php)**

**Location:** Line 62

**Before:**
```php
$stmt = $mysqli->prepare("SELECT id, file_name, file_path, uploaded_at as created_at FROM patient_reports WHERE patient_id=? ORDER BY uploaded_at DESC");
```

**After:**
```php
$stmt = $mysqli->prepare("SELECT id, file_name, file_path, prescription_id, uploaded_at as created_at FROM patient_reports WHERE patient_id=? ORDER BY uploaded_at DESC");
```

**What changed:** Added `prescription_id` to the SELECT query so we can match blood reports to specific prescriptions.

---

#### 2. **Frontend Display Fix (patient_history.html)**

**Location:** Line 965-974

**Before:**
```javascript
} else {
  // Doctor: Show View button if blood report exists
  if(cachedBloodReports.length > 0){
    viewBloodReportBtn = `<button type="button" class="btn btn-view" onclick="openBloodReport('${patientId}')">
      <i class="fas fa-vial"></i> View
    </button>`;
  } else {
    viewBloodReportBtn = '<span style="color:var(--text-muted);font-size:12px">No report</span>';
  }
}
```

**After:**
```javascript
} else {
  // Doctor: Show View button if blood report exists for this prescription
  const reportForPrescription = cachedBloodReports.find(r => r.prescription_id == prescriptionId);
  if(reportForPrescription){
    viewBloodReportBtn = `<button type="button" class="btn btn-view" onclick="openBloodReport('${patientId}', '${prescriptionId}')">
      <i class="fas fa-vial"></i> View
    </button>`;
  } else {
    viewBloodReportBtn = '<span style="color:var(--text-muted);font-size:12px">No report</span>';
  }
}
```

**What changed:**
- Now checks if a blood report exists **for this specific prescription** using `cachedBloodReports.find()`
- Only shows "View" button if a report exists for that prescription
- Passes both `patientId` AND `prescriptionId` to `openBloodReport()` function

---

#### 3. **Blood Report Opening Fix (patient_history.html)**

**Location:** Line 1000-1015

**Before:**
```javascript
function openBloodReport(patientId){
  // Find the latest blood report for this patient from patient_reports table
  if(cachedBloodReports.length === 0){
    alert('No blood reports uploaded for this patient');
    return;
  }
  
  // Open the most recent blood report
  const latestReport = cachedBloodReports[0];
  if(latestReport && latestReport.file_path){
    openPdfModal(latestReport.file_path, latestReport.file_name || 'Blood Report');
  } else {
    alert('No blood report file found');
  }
}
```

**After:**
```javascript
function openBloodReport(patientId, prescriptionId){
  // Find the blood report for this specific prescription
  if(cachedBloodReports.length === 0){
    alert('No blood reports uploaded for this patient');
    return;
  }
  
  // Find report matching the prescription ID
  const report = cachedBloodReports.find(r => r.prescription_id == prescriptionId);
  
  if(report && report.file_path){
    openPdfModal(report.file_path, report.file_name || 'Blood Report');
  } else {
    alert('No blood report found for this prescription');
  }
}
```

**What changed:**
- Function now accepts both `patientId` and `prescriptionId` parameters
- Uses `find()` to locate the blood report matching the specific `prescriptionId`
- Opens the correct report for that prescription
- Shows specific error message if no report found for that prescription

---

## How It Works Now

### For Receptionists:
1. Receptionist sees "Upload Report" button for each prescription
2. Clicks to upload a blood report for a specific prescription
3. The report is saved with the `prescription_id` linked to it

### For Doctors:
1. Doctor sees patient's prescription history
2. Each prescription row shows:
   - **"View" button** if a blood report has been uploaded for THAT specific prescription
   - **"No report" text** if no blood report exists for that prescription
3. Clicking "View" opens the blood report specific to that prescription
4. Different prescriptions now show different blood reports correctly

---

## Data Flow

```
1. Receptionist uploads blood report
   ↓
2. Saved in database: patient_reports table
   - patient_id: Links to patient
   - prescription_id: Links to specific prescription  ← KEY FIX
   - file_path: Location of PDF file
   ↓
3. Doctor views patient history
   ↓
4. Backend sends: All blood reports with prescription_id
   ↓
5. Frontend matches: Each prescription with its blood report
   ↓
6. Display: Correct "View" button only for prescriptions with reports
   ↓
7. Click "View": Opens the specific report for that prescription
```

---

## Example Scenario

**Before Fix:**
- Patient has 3 prescriptions (ID: 101, 102, 103)
- Blood reports uploaded for prescriptions 101 and 103
- Doctor clicks "View" on any prescription → Always shows report from prescription 103 (latest)
- ❌ Wrong report shown

**After Fix:**
- Patient has 3 prescriptions (ID: 101, 102, 103)
- Blood reports uploaded for prescriptions 101 and 103
- Prescription 101: Shows "View" button → Opens report for prescription 101 ✅
- Prescription 102: Shows "No report" text (no upload yet) ✅
- Prescription 103: Shows "View" button → Opens report for prescription 103 ✅
- ✅ Correct report shown for each prescription

---

## Testing Checklist

### Test as Receptionist:
- ✅ Can upload blood report for each prescription separately
- ✅ Upload flow includes prescription_id in URL
- ✅ Each upload is saved with correct prescription link

### Test as Doctor:
- ✅ Prescriptions with uploaded reports show "View" button
- ✅ Prescriptions without reports show "No report" text
- ✅ Clicking "View" opens the correct report for that prescription
- ✅ Different prescriptions show different blood reports
- ✅ No more "same file everywhere" issue

---

## Files Modified

1. **patient_history.php** (Backend)
   - Added `prescription_id` to blood reports query
   - Ensures prescription linking data is sent to frontend

2. **patient_history.html** (Frontend)
   - Modified `renderPrescriptions()` to check for prescription-specific reports
   - Updated `openBloodReport()` to accept and use prescription_id
   - Changed button generation to pass prescription_id

---

## Summary

✅ **Issue:** All prescriptions showed the same (latest) blood report  
✅ **Fix:** Link blood reports to specific prescriptions using prescription_id  
✅ **Result:** Each prescription now shows its own correct blood report  

---

**Last Updated:** 2025-11-25  
**Files Changed:** 2 (patient_history.php, patient_history.html)  
**Lines Modified:** 3 sections
