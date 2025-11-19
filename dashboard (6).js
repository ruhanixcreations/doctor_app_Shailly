document.addEventListener("DOMContentLoaded", () => {
  const gallery = document.getElementById("gallery");

  // ✅ Load per-city video summary
  fetch("dashboard.php?loadGallery=true")
    .then(response => response.text())
    .then(html => {
      gallery.innerHTML = html || "<p class='no-photos'>No videos available.</p>";
    })
    .catch(error => {
      console.error("Error loading gallery:", error);
      gallery.innerHTML = "<p class='no-photos'>Failed to load videos.</p>";
    });

  // --- Modal Logic (only if elements exist) ---
  const addBtn = document.getElementById("addButton");
  const modal = document.getElementById("uploadModal");
  const closeModal = document.getElementById("closeModal");
  const uploadVideoBtn = document.getElementById("uploadVideoBtn");
  const addPhotosBtn = document.getElementById("addPhotosBtn");

  if (addBtn && modal && closeModal) {
    addBtn.addEventListener("click", () => {
      modal.style.display = "flex";
    });

    closeModal.addEventListener("click", () => {
      modal.style.display = "none";
    });

    window.addEventListener("click", (e) => {
      if (e.target === modal) modal.style.display = "none";
    });
  }

  if (uploadVideoBtn) {
    uploadVideoBtn.addEventListener("click", () => {
      window.location.href = "/environment_project/dashboard/upload_video/upload_video.html";
    });
  }

  if (addPhotosBtn) {
    addPhotosBtn.addEventListener("click", () => {
      window.location.href = "/environment_project/dashboard/upload_photos/upload_photos.html";
    });
  }
});

// ✅ Function to open a city gallery and increment its total views
function openCityGallery(city) {
  // First, increment the total view count for that city
  fetch(`/environment_project/dashboard/update_view_count.php?city=${encodeURIComponent(city)}`)
    .then(res => res.json())
    .then(data => {
      console.log("View count updated for city:", city, data);
      // After updating views, redirect to gallery
      window.location.href = `/environment_project/dashboard/city_gallery.html?city=${encodeURIComponent(city)}`;
    })
    .catch(err => {
      console.error("Error updating view count:", err);
      // Redirect anyway if update fails
      window.location.href = `/environment_project/dashboard/city_gallery.html?city=${encodeURIComponent(city)}`;
    });
}

// ✅ Share App Button Logic (Works on mobile, WhatsApp, and Facebook)
document.addEventListener("DOMContentLoaded", () => {
  const shareBtn = document.getElementById("shareAppBtn");
  if (!shareBtn) return;

  const dashboardUrl = "https://ruhanixlegal.in/environment_project/dashboard/dashboard.html"; // real dashboard
  const fbShareUrl = "https://ruhanixlegal.in/environment_project/share.html"; // OG tags page
  const shareText = "Check out this amazing Environment Project Dashboard! 🌱";

  shareBtn.addEventListener("click", async () => {
    // 🟢 Native mobile share (WhatsApp, Telegram, etc.)
    if (navigator.share) {
      try {
        await navigator.share({
          title: "Environment Project",
          text: shareText,
          url: dashboardUrl
        });
        console.log("Shared successfully via native share.");
      } catch (err) {
        console.warn("Share cancelled or failed:", err);
      }
    } 
    // 🔵 Desktop fallback
    else {
      const isFacebook = confirm("Share on Facebook?\nClick 'OK' for Facebook, 'Cancel' to copy link.");

      if (isFacebook) {
        // Open Facebook share dialog with /share.html for proper preview
        window.open(
          `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(fbShareUrl)}`,
          "_blank",
          "width=600,height=400"
        );
      } else {
        // Copy real dashboard URL to clipboard
        try {
          await navigator.clipboard.writeText(dashboardUrl);
          alert("Link copied! You can share it manually.");
        } catch (err) {
          alert("Sharing not supported. Copy this link manually:\n" + dashboardUrl);
        }
      }
    }
  });
});