# Testing Guide for Form Data Preservation Fixes

## 🧪 Quick Testing Instructions

Test all 4 fixes to ensure everything works correctly on both desktop and mobile.

---

## Test 1: Add New Patient Page - Form Data Persistence

### Desktop Testing:
1. Navigate to "Add New Patient" page
2. Fill in the form:
   - Patient Name: "John Doe"
   - Mobile: "1234567890"
   - Age: "35"
   - Weight: "70"
   - Select a doctor
3. Take a photo and click "Create PDF from Photos"
4. Click "View PDF" link (opens in new tab)
5. Close the PDF tab and return to the form
6. ✅ **Expected:** All form fields should still contain the data you entered

### Mobile Testing:
1. Repeat steps 1-5 above on mobile device
2. ✅ **Expected:** Same behavior - data preserved

### Additional Test:
1. Fill the form completely
2. Click "Save & Submit"
3. ✅ **Expected:** Form should be cleared after successful submission

---

## Test 2: Add Prescription Page - Complex Form State Preservation

### Desktop Testing:

#### Test 2A: Print with Saved Prescription
1. Navigate to "Add Prescription" page
2. Select a patient
3. Fill in symptoms: "Fever and headache"
4. Add a medicine:
   - Select medicine: "Paracetamol"
   - Type: "Tablet"
   - Duration: "3 days"
   - Time: Morning, Night
   - Before/After: After meal
5. Click "Add Medicine"
6. Add another medicine (different details)
7. Select blood tests (e.g., "CBC", "Blood Sugar")
8. Set follow-up date
9. Click "Save Draft"
10. Click "Print Prescription"
11. Close the print window
12. ✅ **Expected:** 
    - All medications still in the list
    - Blood tests still selected
    - Symptoms and follow-up date preserved

#### Test 2B: Print While Filling Medicine Form
1. Navigate to "Add Prescription" page
2. Select a patient
3. Fill symptoms
4. Start filling medicine form but DON'T click "Add Medicine":
   - Select medicine: "Aspirin"
   - Type: "Tablet"
   - Duration: "5 days"
5. Save as draft (if available)
6. Click "Print Prescription" (if there's a saved prescription)
7. Close the print window
8. ✅ **Expected:** 
    - Partially filled medicine form should be restored
    - All fields you filled should still be there

### Mobile Testing:
1. Repeat Test 2A on mobile device
2. ✅ **Expected:** Same behavior

### Additional Test:
1. Fill prescription completely
2. Click "Save Draft"
3. ✅ **Expected:** Form data persists after save
4. Now click final "Save"
5. ✅ **Expected:** Form should be cleared after final save

---

## Test 3: Patient History Page - Patient Selection Persistence

### Desktop Testing:

#### Test 3A: View Prescription
1. Navigate to "Patient History" page
2. Select a patient from dropdown (e.g., "Jane Smith")
3. Wait for data to load
4. Click "View" button for any prescription
5. Print window opens in new tab
6. Close the print window
7. ✅ **Expected:** Patient "Jane Smith" should still be selected

#### Test 3B: View PDF Report
1. Select a patient
2. Click "View" button for a blood test report
3. PDF modal opens
4. Click "Close" button
5. ✅ **Expected:** Patient should still be selected

#### Test 3C: Navigation from Another Page
1. Go to "Add Prescription" page
2. Click "View Prescription History" button for a patient
3. ✅ **Expected:** 
    - Patient History page opens
    - The patient should be automatically selected
    - Their data should be displayed

### Mobile Testing:
1. Repeat Test 3A on mobile device
2. Use mobile back button after viewing prescription
3. ✅ **Expected:** Patient still selected

### Additional Test:
1. Select a patient
2. Click dropdown and select another patient
3. Reload the page
4. ✅ **Expected:** Last selected patient should be re-selected automatically

---

## Test 4: Blood Test List Page - Duplicate Prevention

### Desktop Testing:

#### Test 4A: Add Duplicate (Exact Match)
1. Navigate to "Blood Tests" page
2. Note existing test names (e.g., if "CBC" exists)
3. Click "Add Test"
4. Enter:
   - Name: "CBC" (exact match)
   - Price: "500"
5. Click "Save Test"
6. ✅ **Expected:** Error message: "A blood test with this name already exists"

#### Test 4B: Add Duplicate (Case Insensitive)
1. Click "Add Test"
2. Enter:
   - Name: "cbc" (lowercase)
   - Price: "500"
3. Click "Save Test"
4. ✅ **Expected:** Same error message (case-insensitive check)

#### Test 4C: Edit to Duplicate Name
1. Click "Edit" on any existing test (e.g., "Blood Sugar")
2. Change name to existing test name (e.g., "CBC")
3. Click "Update Test"
4. ✅ **Expected:** Error message: "Another blood test with this name already exists"

#### Test 4D: Edit Keeping Same Name
1. Click "Edit" on "CBC"
2. Keep name as "CBC"
3. Change price to "600"
4. Click "Update Test"
5. ✅ **Expected:** Update should succeed (same name for same test is allowed)

### Mobile Testing:
1. Repeat Test 4A on mobile device
2. ✅ **Expected:** Error message displays properly on mobile

---

## 🎯 Cross-Browser Testing

Test on multiple browsers to ensure compatibility:

### Desktop Browsers:
- [ ] Chrome
- [ ] Firefox
- [ ] Safari
- [ ] Edge

### Mobile Browsers:
- [ ] Chrome (Android)
- [ ] Safari (iOS)
- [ ] Samsung Internet
- [ ] Firefox Mobile

---

## 🐛 Common Issues to Watch For

### Issue: Data Not Restored
**Check:**
- Is JavaScript enabled?
- Are browser storage settings blocking sessionStorage?
- Check browser console for errors

### Issue: Duplicate Check Not Working
**Check:**
- Is the page fully loaded?
- Check if `window._blood_tests` is populated
- Verify network tab shows blood tests loaded

### Issue: Patient Selection Lost
**Check:**
- Does Select2 library load properly?
- Check if sessionStorage is accessible
- Verify patient ID format matches

---

## ✅ Success Criteria

All tests should pass with:
- ✅ No JavaScript errors in console
- ✅ No data loss when navigating
- ✅ Clear error messages for duplicates
- ✅ Consistent behavior across devices
- ✅ Smooth user experience

---

## 📝 Reporting Issues

If you find any issues during testing:

1. **Note the exact steps** to reproduce
2. **Check browser console** for error messages
3. **Take screenshots** of the issue
4. **Note browser & device** being used
5. **Check if issue occurs on other browsers**

---

## 🎉 Testing Complete

When all tests pass:
- ✅ Form data preservation working
- ✅ Patient selection sticky
- ✅ Duplicates prevented
- ✅ Mobile responsive
- ✅ Ready for production!

---

**Last Updated:** 2025-11-26  
**Version:** 1.0  
**Status:** All Fixes Implemented ✨
