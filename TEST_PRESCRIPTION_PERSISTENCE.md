# Test Guide: Add Prescription Form Persistence

## Quick Test Instructions

### Test 1: Save Draft Keeps Data ✅
1. Open add prescription page
2. Select a patient
3. Fill in:
   - Symptoms: "Fever, headache"
   - Follow-up date: Any date
   - Add 2-3 medications
   - Select 2-3 blood tests
4. Click **"Save Prescription"** button
5. **Expected:** Alert says "Prescription saved"
6. **Verify:** All fields are still filled (symptoms, medications, blood tests, follow-up)
7. **Result:** ✅ Data should remain visible

### Test 2: Print and Return ✅
1. After saving draft (or with filled form)
2. Click **"Print Prescription"** button
3. New tab opens with print preview
4. Close the print tab or click back
5. **Verify:** All form data is still visible on the prescription page
6. **Result:** ✅ No data lost

### Test 3: Page Reload ✅
1. Fill the form completely (symptoms, meds, blood tests)
2. Press **F5** or click browser refresh
3. **Verify:** Patient is auto-selected (if was selected before)
4. **Verify:** All form data is restored automatically
5. **Result:** ✅ Data persists across page reload

### Test 4: Save Draft Multiple Times ✅
1. Fill form and save draft
2. Modify some fields (add another med, change symptoms)
3. Save draft again
4. Modify again
5. Save draft again
6. **Verify:** Form keeps showing the latest data after each save
7. **Result:** ✅ No clearing, continuous editing possible

### Test 5: Navigate Away and Return ✅
1. Fill the form
2. Click **"View Prescription History"** or any other page link
3. Click browser back button
4. **Verify:** Form data is restored
5. **Result:** ✅ Data persists

### Test 6: Switch Patients ✅
1. Select Patient A, fill form, save draft
2. Switch to Patient B, fill different data, save draft
3. Switch back to Patient A
4. **Verify:** Patient A's saved data is restored
5. **Result:** ✅ Per-patient data storage works

---

## Field-Level Persistence Tests

### Medicine Form:
- [x] Select medicine from dropdown → auto-saved
- [x] Enter custom medicine → auto-saved on blur
- [x] Select type (Tablet/Syrup) → auto-saved on blur
- [x] Select duration → auto-saved on blur
- [x] Check morning/afternoon/night → auto-saved
- [x] Select before/after meal → auto-saved
- [x] Enter notes → auto-saved on blur
- [x] Click "Add Medicine" → all medicines saved

### Main Form:
- [x] Enter symptoms → auto-saved on blur
- [x] Select follow-up date → auto-saved on blur/change
- [x] Select blood tests → auto-saved on change

---

## Mobile Testing

Repeat Test 1, 2, and 4 on:
- [x] Mobile browser (Chrome/Safari)
- [x] Different screen sizes
- [x] Portrait and landscape modes

---

## Edge Cases

### What Should Clear Data:
❌ Closing browser tab → sessionStorage cleared (expected)
❌ After 24 hours → data expires (expected)
❌ Selecting "New Patient" and clearing selection (if implemented)

### What Should NOT Clear Data:
✅ Saving draft
✅ Opening print preview
✅ Reloading page
✅ Navigating to other pages and returning
✅ Modifying and saving multiple times

---

## Troubleshooting

**Problem:** Data not persisting after reload  
**Check:** Browser's sessionStorage is enabled (not in private/incognito mode)

**Problem:** Wrong patient's data showing  
**Check:** Patient ID is correctly stored in sessionStorage key

**Problem:** Old data showing after 24+ hours  
**Expected:** Data expires after 24 hours, this is normal

---

## Success Criteria

✅ Form data remains visible after save draft  
✅ No data loss when navigating to print and back  
✅ Data persists across page reloads  
✅ Each patient's data stored separately  
✅ All form fields participate in auto-save  
✅ Works on both mobile and desktop  

---

**Last Updated:** 2025-11-26
