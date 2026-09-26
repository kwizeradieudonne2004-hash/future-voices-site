/* Future Voices School & Studio — front-end logic
   Talks to the PHP endpoints in this same folder:
   check_availability.php, book_room.php, enroll_course.php, payment_callback.php
*/
(function () {
  "use strict";

  /* ---------- Mobile nav ---------- */
  var toggle = document.querySelector(".nav-toggle");
  var links = document.querySelector(".nav-links");
  if (toggle && links) {
    toggle.addEventListener("click", function () {
      var open = links.style.display === "flex";
      links.style.display = open ? "" : "flex";
      links.style.flexDirection = "column";
      links.style.position = "absolute";
      links.style.top = "84px";
      links.style.left = "0";
      links.style.right = "0";
      links.style.background = "#fbf7ec";
      links.style.padding = "20px 28px";
      links.style.borderBottom = "1px solid rgba(21,20,15,0.12)";
      toggle.setAttribute("aria-expanded", String(!open));
    });
  }

  /* ---------- FAQ accordion ---------- */
  document.querySelectorAll(".faq-item").forEach(function (item) {
    var q = item.querySelector(".faq-q");
    q.addEventListener("click", function () {
      var isOpen = item.getAttribute("data-open") === "true";
      document.querySelectorAll(".faq-item").forEach(function (el) {
        el.setAttribute("data-open", "false");
        el.querySelector(".faq-q").setAttribute("aria-expanded", "false");
      });
      item.setAttribute("data-open", String(!isOpen));
      q.setAttribute("aria-expanded", String(!isOpen));
    });
  });

  /* ================================================================
     ENROLLMENT MODAL  (POST enroll_course.php)
     ================================================================ */
  var overlay = document.getElementById("enroll-modal");
  var modalForm = document.getElementById("enroll-form");
  var modalTitle = document.getElementById("enroll-modal-title");
  var modalFee = document.getElementById("enroll-modal-fee");
  var modalStatus = document.getElementById("enroll-status");
  var modalProgramId = document.getElementById("enroll-program-id");
  var currentProgramFee = 0;

  function openEnrollModal(programId, programName, feeRwf) {
    currentProgramFee = feeRwf;
    modalProgramId.value = programId;
    modalTitle.textContent = "Enroll — " + programName;
    modalFee.textContent = "Enrollment fee: " + formatRwf(feeRwf) + " (paid by mobile money)";
    modalStatus.className = "booking-status";
    modalStatus.textContent = "";
    modalForm.reset();
    overlay.classList.add("show");
    document.getElementById("enroll-name").focus();
  }
  function closeEnrollModal() {
    overlay.classList.remove("show");
  }
  document.querySelectorAll("[data-enroll]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      openEnrollModal(
        btn.getAttribute("data-program-id"),
        btn.getAttribute("data-program-name"),
        parseInt(btn.getAttribute("data-fee"), 10)
      );
    });
  });
  overlay.querySelector(".modal-close").addEventListener("click", closeEnrollModal);
  overlay.addEventListener("click", function (e) {
    if (e.target === overlay) closeEnrollModal();
  });
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") { closeEnrollModal(); }
  });

  modalForm.addEventListener("submit", function (e) {
    e.preventDefault();
    var name = document.getElementById("enroll-name").value.trim();
    var phone = document.getElementById("enroll-phone").value.trim();
    var programId = parseInt(modalProgramId.value, 10);
    var submitBtn = modalForm.querySelector("button[type=submit]");

    setStatus(modalStatus, "pending", "Sending enrollment request…");
    submitBtn.disabled = true;

    postJSON("enroll_course.php", { program_id: programId, name: name, phone: phone })
      .then(function (data) {
        setStatus(modalStatus, "pending", "Requesting " + formatRwf(currentProgramFee) + " from " + phone + " via mobile money. Approve the prompt on your phone…");
        return waitThenConfirm(data.payment_id);
      })
      .then(function () {
        setStatus(modalStatus, "success", "Payment confirmed — you're enrolled! We'll text you the first class details.");
        submitBtn.disabled = false;
      })
      .catch(function (err) {
        setStatus(modalStatus, "error", err.message || "Something went wrong. Please try again.");
        submitBtn.disabled = false;
      });
  });

  /* ================================================================
     ROOM / STUDIO BOOKING WIDGET
     GET check_availability.php  →  POST book_room.php  →  payment_callback.php
     ================================================================ */
  var roomSelect = document.getElementById("room-select");
  var dateInput = document.getElementById("booking-date");
  var slotList = document.getElementById("slot-list");
  var slotHint = document.getElementById("slot-hint");
  var bookingForm = document.getElementById("booking-form");
  var bookingStatus = document.getElementById("booking-status");
  var selectedSlot = null;

  var today = new Date();
  var minDate = today.toISOString().slice(0, 10);
  dateInput.min = minDate;
  dateInput.value = minDate;

  function loadSlots() {
    selectedSlot = null;
    slotList.innerHTML = "";
    var roomId = roomSelect.value;
    var date = dateInput.value;
    if (!roomId || !date) return;

    slotHint.textContent = "Loading availability…";
    fetch("check_availability.php?room_id=" + encodeURIComponent(roomId) + "&date=" + encodeURIComponent(date))
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.error) { slotHint.textContent = data.error; return; }
        slotHint.textContent = "Tap a free slot to hold it.";
        data.slots.forEach(function (s) {
          var chip = document.createElement("button");
          chip.type = "button";
          chip.className = "slot-chip";
          chip.textContent = s.slot;
          chip.setAttribute("aria-pressed", "false");
          if (!s.free) {
            chip.disabled = true;
          } else {
            chip.addEventListener("click", function () {
              slotList.querySelectorAll(".slot-chip").forEach(function (c) { c.setAttribute("aria-pressed", "false"); });
              chip.setAttribute("aria-pressed", "true");
              selectedSlot = s.slot;
            });
          }
          slotList.appendChild(chip);
        });
      })
      .catch(function () { slotHint.textContent = "Couldn't load availability. Check your connection and try again."; });
  }
  roomSelect.addEventListener("change", loadSlots);
  dateInput.addEventListener("change", loadSlots);
  loadSlots();

  bookingForm.addEventListener("submit", function (e) {
    e.preventDefault();
    var name = document.getElementById("booking-name").value.trim();
    var phone = document.getElementById("booking-phone").value.trim();
    var submitBtn = bookingForm.querySelector("button[type=submit]");

    if (!selectedSlot) {
      setStatus(bookingStatus, "error", "Pick a free time slot first.");
      return;
    }

    setStatus(bookingStatus, "pending", "Holding your slot…");
    submitBtn.disabled = true;

    postJSON("book_room.php", {
      room_id: parseInt(roomSelect.value, 10),
      date: dateInput.value,
      slot: selectedSlot,
      name: name,
      phone: phone
    })
      .then(function (data) {
        setStatus(bookingStatus, "pending", "Requesting " + formatRwf(data.amount_rwf) + " from " + phone + " via mobile money. Approve the prompt on your phone…");
        return waitThenConfirm(data.payment_id);
      })
      .then(function () {
        setStatus(bookingStatus, "success", "Booking confirmed for " + dateInput.value + ", " + selectedSlot + ". Cancellations need 2+ days' notice.");
        submitBtn.disabled = false;
        loadSlots();
      })
      .catch(function (err) {
        setStatus(bookingStatus, "error", err.message || "That slot may have just been taken. Pick another.");
        submitBtn.disabled = false;
        loadSlots();
      });
  });

  /* ---------- shared helpers ---------- */
  function postJSON(url, body) {
    return fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body)
    }).then(function (r) {
      return r.json().then(function (data) {
        if (!r.ok) throw new Error(data.error || "Request failed.");
        return data;
      });
    });
  }

  // Demo/mock payment mode (see includes/payment.php, FVSS_PAYMENTS_MODE):
  // the front end waits briefly to simulate the mobile-money prompt, then
  // calls payment_callback.php itself — standing in for the provider's
  // webhook so the flow is testable end to end before real MoMo/Airtel
  // credentials are wired up. Remove this simulated wait once
  // FVSS_PAYMENTS_MODE=live and the real webhook is calling
  // payment_callback.php directly.
  function waitThenConfirm(paymentId) {
    return new Promise(function (resolve) { setTimeout(resolve, 2600); })
      .then(function () {
        return postJSON("payment_callback.php", { payment_id: paymentId });
      });
  }

  function setStatus(el, kind, message) {
    el.className = "booking-status show " + kind;
    el.textContent = message;
  }

  function formatRwf(amount) {
    return "RWF " + Number(amount).toLocaleString("en-US");
  }
})();
